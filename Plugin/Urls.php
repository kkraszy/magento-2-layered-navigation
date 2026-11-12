<?php

declare(strict_types=1);

namespace Kkkonrad\LayeredNavigation\Plugin;

use Kkkonrad\LayeredNavigation\Model\Attribute;
use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\LayeredNavigation\Block\Navigation\FilterRenderer;
use Magento\Swatches\Block\LayeredNavigation\RenderLayered;

/**
 * Option links toggle their value within the attribute's list instead of replacing it:
 * on ?color=49, "50" links to ?color=49,50 and "49" (or its remove button) to no colour.
 */
class Urls
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $url,
        private readonly Escaper $escaper
    ) {
    }

    public function toggle(string $code, string $value): string
    {
        $selected = Attribute::parse($this->request->getParam($code));
        $selected = in_array($value, $selected, true)
            ? array_diff($selected, [$value])
            : [...$selected, $value];
        $url = $this->url->getUrl('*/*/*', [
            '_current' => true,
            '_use_rewrite' => true,
            '_query' => [$code => $selected ? implode(Attribute::SEPARATOR, $selected) : null, 'p' => null],
        ]);
        return str_replace('%2C', Attribute::SEPARATOR, $url);
    }

    /** Untyped: RenderLayered (a block) has a getUrl() too and gets this plugin as well. */
    public function afterGetUrl($item, $result)
    {
        return $item instanceof Item && $item->getFilter() instanceof Attribute
            ? $this->toggle($item->getFilter()->getRequestVar(), (string)$item->getValue())
            : $result;
    }

    public function afterGetRemoveUrl(Item $item, $result)
    {
        return $this->afterGetUrl($item, $result);
    }

    public function aroundBuildUrl(RenderLayered $subject, callable $proceed, $attributeCode, $optionId)
    {
        return $this->toggle((string)$attributeCode, (string)$optionId);
    }

    /**
     * Swatch templates (Hyvä's and Luma's) have no selected state. A selected swatch is the one
     * whose link toggles it off, so mark the anchor carrying that exact href.
     */
    public function afterToHtml(RenderLayered $subject, $html)
    {
        if (!preg_match('/data-attribute-code="([^"]+)"/', (string)$html, $m)) {
            return $html;
        }
        $code = html_entity_decode($m[1]);
        foreach (Attribute::parse($this->request->getParam($code)) as $id) {
            $href = 'href="' . $this->escaper->escapeUrl($this->toggle($code, $id)) . '"';
            $html = str_replace($href, $href . ' aria-current="true"', $html);
        }
        return $html;
    }

    /**
     * Our option list for every theme. Set here, not in layout: Hyvä and theme layouts
     * redeclare this block with their own template after module layouts are merged.
     */
    public function beforeRender(FilterRenderer $subject, $filter): void
    {
        $subject->setTemplate('Kkkonrad_LayeredNavigation::filter.phtml');
    }
}
