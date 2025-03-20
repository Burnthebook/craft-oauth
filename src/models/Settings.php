<?php

namespace burnthebook\craftoauth\models;

use Craft;
use craft\base\Model;

/**
 * OAuth for Craft CMS settings
 */
class Settings extends Model
{
    public array $providers = [];

    /**
     * Returns an array of rules for validating the 'providers' attribute.
     *
     * @return array An array of rules for validating the 'providers' attribute.
    */
    public function rules(): array
    {
        return [
            ['providers', 'safe'],
        ];
    }
}
