<?php

namespace yellowrobot\craftdispatch\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use yellowrobot\craftdispatch\CraftDispatch;
use yellowrobot\craftdispatch\elements\EmailTemplate;

class HookSelectField extends BaseNativeField
{
    public string $attribute = 'handle';

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        $currentHandle = $element instanceof EmailTemplate ? $element->handle : null;
        $hookOptions = CraftDispatch::$plugin->hook->getAvailableHookOptions($currentHandle);

        // No choices to make — hide field entirely (metadata() shows it read-only)
        if ($currentHandle && count($hookOptions) === 1 && $hookOptions[0]['value'] === $currentHandle) {
            return null;
        }

        $options = [['label' => 'Select a hook…', 'value' => '']];
        foreach ($hookOptions as $opt) {
            $options[] = $opt;
        }

        return Craft::$app->getView()->renderTemplate('_includes/forms/select.twig', [
            'id' => $this->id(),
            'describedBy' => $this->describedBy($element, $static),
            'name' => $this->attribute(),
            'value' => $this->value($element),
            'options' => $options,
            'disabled' => $static,
        ]);
    }
}
