<?php

namespace burnthebook\craftoauth;

use Craft;
use yii\base\Event;
use craft\base\Model;
use craft\helpers\Cp;
use craft\base\Plugin;
use craft\base\Element;
use craft\elements\User;
use craft\web\UrlManager;
use craft\models\UserGroup;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\models\FieldLayoutTab;
use craft\fields\Table as TableField;
use craft\elements\User as UserElement;
use craft\events\RegisterUrlRulesEvent;
use craft\fieldlayoutelements\CustomField;
use burnthebook\craftoauth\models\Settings;
use craft\events\RegisterElementTableAttributesEvent;


use craft\elements\Entry;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\PrepareElementQueryForTableAttributeEvent;



/**
 * OAuth for Craft CMS plugin
 *
 * @method static OAuth getInstance()
 * @method Settings getSettings()
 * @author Burnthebook <support@burnthebook.co.uk>
 * @copyright Burnthebook
 * @license https://craftcms.github.io/license/ Craft License
 */
class OAuth extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        $configPath = Craft::getAlias('@config/oauth.php');
        $config = [];
    
        if (file_exists($configPath)) {
            $config = require $configPath;
        }

        return [
            'settings' => $config,
            'components' => [
                'oauthService' => \burnthebook\craftoauth\services\OauthService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function () {
            Craft::info('OAuth plugin loaded', __METHOD__);
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('craft-oauth/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getEffectiveSettings(),
        ]);
    }

    public function getEffectiveSettings(): Settings
    {
        $configOverrides = Craft::$app->config->getConfigFromFile('oauth');
    
        if (!empty($configOverrides)) {
            $settings = new Settings($configOverrides);
        } else {
            $settings = $this->getSettings();
        }
    
        return $settings;
    }

    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/5.x/extend/events.html to get started)
        $this->registerUrlRules();
        $this->onPluginInstall();
        $this->registerCpTableAttributes();
    }

    /**
     * Handle plugin install event
     *
     * @return void
     */
    protected function onPluginInstall(): void
    {
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    $this->createFields();
                }
            }
        );
    }

    /**
     * Register site routes for login and callback
     *
     * @return void
     */
    protected function registerUrlRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['oauth/login/<provider>'] = 'craft-oauth/auth/login';
                $event->rules['oauth/callback/<provider>'] = 'craft-oauth/auth/callback';
            }
        );
    }

    /**
     * Register CP table attributes
     * 
     * @return void
     */
    protected function registerCpTableAttributes() : void
    {
        Event::on(
            User::class,
            User::EVENT_REGISTER_TABLE_ATTRIBUTES,
            function(RegisterElementTableAttributesEvent $event) {
                $event->tableAttributes['oauthProviders'] = [
                    'label' => 'OAuth Providers',
                    'sortable' => true,
                ];
            }
        );

        Event::on(
            Element::class,
            Element::EVENT_DEFINE_ATTRIBUTE_HTML,
            function (DefineAttributeHtmlEvent $event) {
                if ($event->attribute === 'oauthProviders') {
                    $user = $event->sender;
                    $providers = $user->getFieldValue('oauthProviders') ?? [];
        
                    if (empty($providers)) {
                        $event->html = '<span style="color:#999;">—</span>';
                        return;
                    }
        
                    $icons = array_map(function($row) {
                        $provider = strtolower($row['provider']);
                        switch ($provider) {
                            case 'google':
                                return '<span class="status-label green"><span class="status green"></span><span class="status-label-text">Google</span></span>';
                            case 'github':
                                return '<span class="status-label sky"><span class="status sky"></span><span class="status-label-text">GitHub</span></span>';
                            case 'facebook':
                                return '<span class="status-label blue"><span class="status blue"></span><span class="status-label-text">Facebook</span></span>';
                            case 'instagram':
                                return '<span class="status-label red"><span class="status red"></span><span class="status-label-text">Instagram</span></span>';
                            case 'linkedin':
                                return '<span class="status-label violet"><span class="status violet"></span><span class="status-label-text">LinkedIn</span></span>';
                            default:
                                return '<span class="status-label black"><span class="status black"></span><span class="status-label-text">' . ucfirst($provider) . '</span></span>';
                        }
                    }, $providers);
        
                    $event->html = implode('<br>', $icons);
                }
            }
        );
    }

    /**
     * Creates the necessary user group and field for the OAuth plugin.
     *
     * @throws \Throwable if something goes wrong when saving the user group or field.
    */
    protected function createFields(): void
    {
        $userGroupsService = Craft::$app->getUserGroups();
        $existingGroups = $userGroupsService->getAllGroups();
        $groupExists = array_filter($existingGroups, fn ($group) => $group->name === 'OAuth Users');

        if (empty($groupExists)) {
            $group = new UserGroup();
            $group->name = 'OAuth Users';
            $group->handle = 'oauthUsers';
            $userGroupsService->saveGroup($group);
        }

        // Create Table Field
        $field = Craft::$app->fields->getFieldByHandle('oauthProviders');
        if (!$field) {
            $field = new TableField([
                'name' => 'OAuth Providers',
                'handle' => 'oauthProviders',
                'columns' => [
                    'provider' => [
                        'heading' => 'Provider',
                        'handle' => 'provider',
                        'type' => 'singleline',
                    ],
                    'providerId' => [
                        'heading' => 'Provider ID',
                        'handle' => 'providerId',
                        'type' => 'singleline',
                    ]
                ],
                'defaults' => [],
            ]);

            Craft::$app->fields->saveField($field);
        }

        // Attach to User Field Layout
        $userLayout = Craft::$app->fields->getLayoutByType(UserElement::class);
        $tabs = $userLayout->getTabs();

        if (!empty($tabs)) {
            $firstTab = $tabs[0];
            $elements = $firstTab->getElements();

            // Avoid duplicate
            $alreadyAssigned = array_filter($elements, fn ($el) => $el instanceof CustomField && $el->getField()->handle === 'oauthProviders');
            if (empty($alreadyAssigned)) {
                $elements[] = new CustomField($field);
                $firstTab->setElements($elements);
            }
        } else {
            // Create new tab with field
            $tab = new FieldLayoutTab();
            $tab->name = 'OAuth';
            $tab->setLayout($userLayout);
            $tab->setElements([
                new CustomField($field)
            ]);
            $userLayout->setTabs([$tab]);
        }

        // Save updated layout
        Craft::$app->fields->saveLayout($userLayout);

        Craft::info('OAuth plugin installed: User group and field created.', __METHOD__);
    }

}
