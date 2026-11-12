<?php

declare(strict_types=1);

namespace Kkkonrad\LayeredNavigation\Model;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder;
use Magento\Catalog\Model\Layer\Filter\ItemFactory;
use Magento\CatalogSearch\Model\Layer\Filter\Attribute as CoreAttribute;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Filter\StripTags;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Attribute filter that takes several options: ?color=49,50 matches either (OR inside one
 * attribute, AND across attributes). The filter stays listed while active, with its options
 * counted as if it were not applied.
 */
class Attribute extends CoreAttribute
{
    public const SEPARATOR = ',';

    private array $selected = [];

    public function __construct(
        ItemFactory $filterItemFactory,
        StoreManagerInterface $storeManager,
        Layer $layer,
        DataBuilder $itemDataBuilder,
        StripTags $tagFilter,
        private readonly Facets $facets,
        array $data = []
    ) {
        parent::__construct($filterItemFactory, $storeManager, $layer, $itemDataBuilder, $tagFilter, $data);
    }

    /** Option ids from a request value; anything but digits is dropped. */
    public static function parse($value): array
    {
        $parts = is_array($value) ? $value : explode(self::SEPARATOR, (string)$value);
        return array_values(array_unique(array_filter(array_map('trim', $parts), 'ctype_digit')));
    }

    public function apply(RequestInterface $request)
    {
        $this->selected = self::parse($request->getParam($this->_requestVar));
        if (!$this->selected) {
            return $this;
        }

        $code = $this->getAttributeModel()->getAttributeCode();
        $this->getLayer()->getProductCollection()->addFieldToFilter(
            $code,
            count($this->selected) === 1 ? $this->selected[0] : $this->selected
        );
        foreach ($this->selected as $value) {
            $label = $this->getOptionText($value);
            $this->getLayer()->getState()->addFilter(
                $this->_createItem(is_array($label) ? implode(', ', $label) : (string)$label, $value)
            );
        }
        return $this;
    }

    public function getSelected(): array
    {
        return $this->selected;
    }

    protected function _getItemsData()
    {
        $attribute = $this->getAttributeModel();
        $code = $attribute->getAttributeCode();
        $facets = $this->selected
            ? $this->facets->without($this->getLayer(), $code)->getFacetedData($code)
            : $this->getLayer()->getProductCollection()->getFacetedData($code);
        $withResultsOnly = $this->getAttributeIsFilterable($attribute) === static::ATTRIBUTE_OPTIONS_ONLY_WITH_RESULTS;

        foreach ($attribute->getFrontend()->getSelectOptions() as $option) {
            $value = (string)$option['value'];
            if ($value === '' || is_array($option['value'])) {
                continue;
            }
            $count = (int)($facets[$value]['count'] ?? 0);
            if ($withResultsOnly && !$count && !in_array($value, $this->selected, true)) {
                continue;
            }
            $this->itemDataBuilder->addItemData(strip_tags((string)$option['label']), $value, $count);
        }
        return $this->itemDataBuilder->build();
    }

    protected function _initItems()
    {
        parent::_initItems();
        foreach ($this->_items as $item) {
            $item->setData('selected', in_array((string)$item->getValue(), $this->selected, true));
        }
        return $this;
    }
}
