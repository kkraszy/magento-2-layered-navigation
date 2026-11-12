<?php

declare(strict_types=1);

namespace Kkkonrad\LayeredNavigation\Model;

use Magento\Catalog\Model\Layer;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Framework\Api\Search\SearchCriteriaBuilderFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Counts for a multi-select filter must ignore that filter's own selection, or picking "Red"
 * would leave "Red" as the only colour on offer. OpenSearch cannot drop one filter from a
 * built request, so this records how the layer collection was made (plugins on the provider
 * and on addFieldToFilter) and builds a second collection with that one field left out.
 * One extra search per active attribute - the usual trade-off for multi-select facets.
 */
class Facets implements ArgumentInterface
{
    private \WeakMap $providers;
    private \WeakMap $filters;
    /** @var Collection[] */
    private array $without = [];

    public function __construct(private readonly SearchCriteriaBuilderFactory $criteriaBuilders)
    {
        $this->providers = new \WeakMap();
        $this->filters = new \WeakMap();
    }

    /** Category and search layers use two unrelated provider interfaces; both land here. */
    public function afterGetCollection($subject, $result)
    {
        $this->providers[$result] = $subject;
        return $result;
    }

    public function beforeAddFieldToFilter(Collection $subject, $field, $condition = null)
    {
        $list = $this->filters[$subject] ?? [];
        $list[] = [$field, $condition];
        $this->filters[$subject] = $list;
        return null;
    }

    /** The layer's product collection with every filter applied except $field's own. */
    public function without(Layer $layer, string $field): Collection
    {
        $main = $layer->getProductCollection();
        $provider = $this->providers[$main] ?? null;
        if (!$provider) {
            return $main;
        }
        $key = spl_object_id($main) . ':' . $field;
        if (!isset($this->without[$key])) {
            $fresh = $provider->getCollection($layer->getCurrentCategory());
            // Core hands every fulltext collection the same shared criteria builder and never
            // clears its filters, so without a private one this would inherit $field anyway.
            $fresh->setSearchCriteriaBuilder($this->criteriaBuilders->create());
            $layer->prepareProductCollection($fresh);
            foreach ($this->filters[$main] ?? [] as [$f, $condition]) {
                if ($f !== $field) {
                    $fresh->addFieldToFilter($f, $condition);
                }
            }
            $this->without[$key] = $fresh;
        }
        return $this->without[$key];
    }

    /** [min, max] price of the current listing ignoring the price filter, for the slider. */
    public function priceBounds(Layer $layer): array
    {
        $collection = $this->without($layer, 'price');
        $collection->getFacetedData('price'); // runs the search, so the SQL below sees its result
        return [(int)floor((float)$collection->getMinPrice()), (int)ceil((float)$collection->getMaxPrice())];
    }
}
