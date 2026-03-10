<?php

namespace yellowrobot\craftdispatch;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\fieldlayoutelements\TextField;
use craft\fieldlayoutelements\TitleField;
use craft\models\FieldLayout;
use craft\services\Elements;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use yellowrobot\craftdispatch\elements\EmailTemplate;
use yellowrobot\craftdispatch\fieldlayoutelements\TextareaField;
use yellowrobot\craftdispatch\engines\EmailEngine;
use yellowrobot\craftdispatch\engines\SlackEngine;
use yellowrobot\craftdispatch\engines\SmsEngine;
use yellowrobot\craftdispatch\engines\WebhookEngine;
use yellowrobot\craftdispatch\models\Settings;
use yellowrobot\craftdispatch\services\EmailService;
use yellowrobot\craftdispatch\services\HookService;
use yellowrobot\craftdispatch\services\LogService;
use yii\base\Event;

/**
 * Dispatch - Template management and event-driven sending for Craft CMS
 *
 * @property-read Settings $settings
 * @property-read HookService $hook
 * @property-read EmailService $email
 * @property-read LogService $log
 */
class CraftDispatch extends Plugin
{
    public static CraftDispatch $plugin;

    public string $schemaVersion = '1.0.0';
    public bool $hasCpSection = true;

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->setComponents([
            'hook' => HookService::class,
            'email' => EmailService::class,
            'log' => LogService::class,
        ]);

        $this->_registerElementTypes();
        $this->_registerNativeFields();
        $this->_registerCpRoutes();
        $this->_registerPermissions();

        Craft::$app->onInit(function () {
            if (!$this->isInstalled) {
                return;
            }
            $this->hook->registerListeners();
        });

        Craft::info('Dispatch plugin loaded', __METHOD__);
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = 'Dispatch';
        $item['subnav'] = [
            'templates' => ['label' => 'Templates', 'url' => 'craft-dispatch'],
            'logs' => ['label' => 'Logs', 'url' => 'craft-dispatch/logs'],
        ];

        return $item;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    private function _registerElementTypes(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = EmailTemplate::class;
            }
        );
    }

    private function _registerNativeFields(): void
    {
        Event::on(
            FieldLayout::class,
            FieldLayout::EVENT_DEFINE_NATIVE_FIELDS,
            function (DefineFieldLayoutFieldsEvent $event) {
                if ($event->sender->type !== EmailTemplate::class) {
                    return;
                }

                $event->fields[] = [
                    'class' => TitleField::class,
                    'mandatory' => true,
                ];

                $event->fields[] = [
                    'class' => TextField::class,
                    'attribute' => 'subject',
                    'label' => 'Subject',
                    'instructions' => 'Twig is supported. E.g., `Welcome, {{ name }}!`',
                    'mandatory' => true,
                    'required' => true,
                ];

                $event->fields[] = [
                    'class' => TextareaField::class,
                    'attribute' => 'htmlBody',
                    'label' => 'HTML Body',
                    'instructions' => 'Twig template for the HTML email body.',
                    'mandatory' => true,
                    'required' => true,
                    'rows' => 16,
                    'code' => true,
                ];

                $event->fields[] = [
                    'class' => TextareaField::class,
                    'attribute' => 'textBody',
                    'label' => 'Plain Text Body',
                    'instructions' => 'Optional. If blank, plain text will be auto-generated from the HTML body.',
                    'mandatory' => true,
                    'required' => false,
                    'rows' => 8,
                    'code' => true,
                ];
            }
        );
    }

    private function _registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['craft-dispatch'] = 'craft-dispatch/templates/index';
                $event->rules['craft-dispatch/new'] = 'craft-dispatch/templates/create';
                $event->rules['craft-dispatch/edit/<elementId:\d+>'] = 'elements/edit';
                $event->rules['craft-dispatch/logs'] = 'craft-dispatch/logs/index';
                $event->rules['craft-dispatch/logs/resend'] = 'craft-dispatch/logs/resend';
                $event->rules['craft-dispatch/logs/<id:\d+>'] = 'craft-dispatch/logs/detail';
            }
        );
    }

    /** @var array<string, class-string<\yellowrobot\craftdispatch\engines\AbstractEngine>> */
    private array $engines = [];

    public function getEngines(): array
    {
        if (empty($this->engines)) {
            $this->engines = [
                'email' => EmailEngine::class,
                'slack' => SlackEngine::class,
                'webhook' => WebhookEngine::class,
                'sms' => SmsEngine::class,
            ];
        }
        return $this->engines;
    }

    public function getEngine(string $channel): ?string
    {
        return $this->getEngines()[$channel] ?? null;
    }

    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'Dispatch',
                    'permissions' => [
                        'craft-dispatch:manage' => [
                            'label' => 'Manage Dispatch',
                        ],
                    ],
                ];
            }
        );
    }
}
