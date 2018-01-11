<?php

namespace DMK\Mkdam2fal\ViewHelpers;


/**
 * This ViewHelper filter and counts elements of the specified array or countable object.
 *
 * = Examples =
 *
 * <code title="Count array elements">
 * <f:count filter="function($v) { return $v % 2 == 0; }" subject="{0:1, 1:2, 2:3, 3:4}" />
 * </code>
 * <output>
 * 3
 * </output>
 *
 * @api
 */
class CountFilterViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper
{

    /**
     * @var boolean
     */
    protected $escapingInterceptorEnabled = false;

    /**
     * Counts the items of a given property.
     *
     * @param string $filter
     * @param array  $subject The array or \Countable to be counted
     *
     * @return int The number of elements
     * @throws \TYPO3\CMS\Fluid\Core\ViewHelper\Exception
     * @api
     */
    public function render($filter, $subject = null)
    {
        $result = 0;

        if ($subject === null) {
            $subject = $this->renderChildren();
        }
        if (is_object($subject) && !$subject instanceof \Countable) {
            throw new \TYPO3\CMS\Fluid\Core\ViewHelper\Exception('CountFilterViewHelper only supports arrays and objects implementing \Countable interface. Given: "' . get_class($subject) . '"');
        }

        $func = create_function('$subject', 'return ' . $filter);

        foreach ($subject as $value) {
            if ($func($value)) {
                $result++;
            }
        }
        return $result;
    }
}
