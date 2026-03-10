<?php

namespace yellowrobot\craftdispatch\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\helpers\Cp;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use yellowrobot\craftdispatch\CraftDispatch;
use yellowrobot\craftdispatch\elements\db\EmailTemplateQuery;

class EmailTemplate extends Element
{
    public ?string $handle = null;
    public ?string $subject = null;
    public ?string $htmlBody = null;
    public ?string $textBody = null;

    public function getHookStatus(): string
    {
        $registeredHandles = CraftDispatch::$plugin->hook->getRegisteredHandles();
        if (!in_array($this->handle, $registeredHandles, true)) {
            return 'orphaned';
        }
        return $this->enabled ? 'connected' : 'draft';
    }

    public static function displayName(): string
    {
        return 'Email Template';
    }

    public static function pluralDisplayName(): string
    {
        return 'Email Templates';
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function find(): EmailTemplateQuery
    {
        return new EmailTemplateQuery(static::class);
    }

    public function getFieldLayout(): ?FieldLayout
    {
        $layout = new FieldLayout();
        $layout->type = static::class;

        $tab = new \craft\models\FieldLayoutTab();
        $tab->name = 'Content';
        $tab->setLayout($layout);
        $tab->setElements([
            new \yellowrobot\craftdispatch\fieldlayoutelements\HookSelectField([
                'attribute' => 'handle',
                'label' => 'Hook',
                'instructions' => 'Select which config hook this template is linked to.',
                'required' => true,
            ]),
            new \craft\fieldlayoutelements\TitleField(),
            new \craft\fieldlayoutelements\TextField([
                'attribute' => 'subject',
                'label' => 'Subject',
                'instructions' => 'Twig is supported. E.g., `Welcome, {{ name }}!`',
                'required' => true,
            ]),
            new \yellowrobot\craftdispatch\fieldlayoutelements\TextareaField([
                'attribute' => 'htmlBody',
                'label' => 'HTML Body',
                'instructions' => 'Twig template for the HTML email body.',
                'required' => true,
                'rows' => 16,
                'code' => true,
            ]),
            new \yellowrobot\craftdispatch\fieldlayoutelements\TextareaField([
                'attribute' => 'textBody',
                'label' => 'Plain Text Body',
                'instructions' => 'Optional. If blank, plain text will be auto-generated from the HTML body.',
                'required' => false,
                'rows' => 8,
                'code' => true,
            ]),
        ]);

        $layout->setTabs([$tab]);

        return $layout;
    }

    protected static function defineSources(?string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => 'All Templates',
                'criteria' => [],
            ],
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'handle' => 'Handle',
            'hookStatus' => 'Hook Status',
            'subject' => 'Subject',
            'dateUpdated' => Craft::t('app', 'Date Updated'),
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['title', 'handle', 'hookStatus', 'subject', 'dateUpdated'];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'handle' => 'Handle',
            'dateCreated' => Craft::t('app', 'Date Created'),
            'dateUpdated' => Craft::t('app', 'Date Updated'),
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['handle', 'subject'];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        if ($attribute === 'hookStatus') {
            return match ($this->hookStatus) {
                'connected' => '<span class="status green" title="Connected to config hook"></span> Connected',
                'draft' => '<span class="status yellow" title="Connected but disabled — needs content"></span> Needs Content',
                'orphaned' => '<span class="status orange" title="No matching hook in config"></span> Orphaned',
            };
        }

        if ($attribute === 'title') {
            $url = $this->getCpEditUrl();
            return Html::a(Html::encode($this->title), $url);
        }

        return parent::tableAttributeHtml($attribute);
    }

    protected static function defineActions(?string $source = null): array
    {
        return [
            Delete::class,
        ];
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['handle', 'subject', 'htmlBody'], 'required'];
        $rules[] = [['handle'], 'string', 'max' => 255];
        $rules[] = [['handle'], 'match', 'pattern' => '/^[a-z0-9\-]+$/'];
        $rules[] = [['subject'], 'string', 'max' => 255];

        return $rules;
    }

    public function canView(\craft\elements\User $user): bool
    {
        return $user->can('craft-dispatch:manage');
    }

    protected static function includeSetStatusAction(): bool
    {
        return false;
    }

    public function canSave(\craft\elements\User $user): bool
    {
        return $user->can('craft-dispatch:manage');
    }

    public function canDuplicate(\craft\elements\User $user): bool
    {
        return false;
    }

    public function canCreateDrafts(\craft\elements\User $user): bool
    {
        return false;
    }

    public function canDelete(\craft\elements\User $user): bool
    {
        if (!$user->can('craft-dispatch:manage')) {
            return false;
        }

        // Prevent deletion of templates tied to a config hook
        if ($this->handle && CraftDispatch::$plugin->hook->getHookByHandle($this->handle)) {
            return false;
        }

        return true;
    }

    public function getUriFormat(): ?string
    {
        return null;
    }

    protected function previewTargets(): array
    {
        return [];
    }

    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("craft-dispatch/edit/{$this->id}");
    }

    // ─── Sidebar ───────────────────────────────────────────────

    protected function metaFieldsHtml(bool $static): string
    {
        return parent::metaFieldsHtml($static);
    }

    protected function metadata(): array
    {
        $metadata = [];

        if (!$this->handle) {
            return $metadata;
        }

        // Show hook handle in metadata
        $metadata['Hook'] = Html::tag('code', Html::encode($this->handle));

        $hook = CraftDispatch::$plugin->hook->getHookByHandle($this->handle);
        if (!$hook) {
            $metadata['Hook Status'] = Html::tag('span', 'No config hook found for this handle.', ['style' => 'color:#9ca3af;']);
            return $metadata;
        }

        // Event
        $eventClass = $hook->eventClass ? (new \ReflectionClass($hook->eventClass))->getShortName() : null;
        if ($eventClass) {
            $metadata['Event'] = Html::tag('code', Html::encode("{$eventClass}::{$hook->eventName}"));
        }

        // Channels
        $metadata['Channels'] = implode(', ', $hook->getChannels());

        // Send Mode
        $metadata['Send Mode'] = $hook->sendMode === 'individual' ? 'Individual' : 'List';

        // To / CC / BCC
        $recipientFields = [
            'To' => $hook->recipientResolver,
            'CC' => $hook->ccResolver,
            'BCC' => $hook->bccResolver,
        ];

        foreach ($recipientFields as $label => $resolver) {
            if ($resolver === null) {
                continue;
            }
            $resolved = $this->_resolveRecipientClosure($resolver);
            $metadata[$label] = $resolved['resolved'] ? $resolved['value'] : 'Dynamic (per entry)';
        }

        // Condition
        if ($hook->condition) {
            $metadata['Condition'] = '<span class="status green"></span> Has guard';
        }

        // Layout
        if ($hook->layoutTemplate) {
            $metadata['Layout'] = Html::tag('code', Html::encode($hook->layoutTemplate));
        }

        return $metadata;
    }

    private function _resolveRecipientClosure(\Closure $closure): array
    {
        try {
            // Call with a bare event — if the closure doesn't touch event data, it's static
            $result = $closure(new \yii\base\Event());

            if (is_string($result)) {
                $emails = array_map('trim', explode(',', $result));
            } elseif (is_array($result)) {
                $emails = $result;
            } else {
                return ['resolved' => false, 'value' => null];
            }

            $resolved = implode(', ', array_filter($emails));
            if (empty($resolved)) {
                return ['resolved' => false, 'value' => null];
            }

            return ['resolved' => true, 'value' => $resolved];
        } catch (\Throwable) {
            // Closure needs event data — recipient is dynamic
            return ['resolved' => false, 'value' => null];
        }
    }

    // ─── Persistence ───────────────────────────────────────────

    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            $data = [
                'handle' => $this->handle,
                'subject' => $this->subject,
                'htmlBody' => $this->htmlBody,
                'textBody' => $this->textBody,
            ];

            Db::upsert('{{%craftdispatch_templates}}', [
                'id' => $this->id,
                ...$data,
            ], $data);
        }

        parent::afterSave($isNew);
    }
}
