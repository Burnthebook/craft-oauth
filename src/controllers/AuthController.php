<?php

namespace burnthebook\craftoauth\controllers;

use Craft;
use yii\web\Response;
use craft\elements\User;
use craft\web\Controller;
use craft\helpers\UrlHelper;
use burnthebook\craftoauth\OAuth;

class AuthController extends Controller
{
    protected array|int|bool $allowAnonymous = ['login', 'callback'];

    /**
     * Redirects the user to the authorization URL for the specified provider.
     *
     * @param string $provider The name of the OAuth provider.
     * @return Response The response object.
     * @throws NotFoundHttpException if the specified provider is unknown.
    */
    public function actionLogin(string $provider): Response
    {
        $url = OAuth::getInstance()->oauthService->getAuthorizationUrl($provider);

        if ($url) {
            return $this->redirect($url);
        }

        throw new \yii\web\NotFoundHttpException("Unknown provider: $provider");
    }

    /**
     * Handles the callback for OAuth login.
     *
     * @param string $provider The provider used for OAuth login.
     * @return Response The response from the OAuth login.
    */
    public function actionCallback(string $provider): Response
    {
        Craft::info("Handling OAuth callback for provider: {$provider}", 'oauth');

        $oauthService = OAuth::getInstance()->oauthService;
        $result = $oauthService->handleCallback($provider);
        Craft::info("Raw OAuth callback response: " . print_r($result, true), 'oauth');

        if (!$result) {
            Craft::error("OAuth login failed for provider: {$provider}", 'oauth');
            return $this->asJson(['error' => 'OAuth login failed']);
        }

        $oauthUser = $result['user'];
        $email = $oauthUser->getEmail();
        $providerId = $oauthUser->getId();
        $name = $oauthUser->getName();

        Craft::info("OAuth user details: email={$email}, providerId={$providerId}, name={$name}", 'oauth');

        if (!$email) {
            Craft::error("No email returned from provider: {$provider}", 'oauth');
            return $this->asJson(['error' => 'No email returned from provider']);
        }

        Craft::info("OAuth user data retrieved: email={$email}, providerId={$providerId}, name={$name}", 'oauth');

        // Step 1: Get or create the user
        $craftUser = $this->findOrCreateUser($email, $name);
        Craft::info("User processed: ID={$craftUser->id}, email={$craftUser->email}", 'oauth');

        // Step 2: Assign to OAuth Users group
        $this->assignUserToGroup($craftUser, 'oauthUsers');
        Craft::info("User assigned to group 'oauthUsers': ID={$craftUser->id}", 'oauth');

        // Step 3: Update oauthProviders field
        $this->updateOauthProvidersField($craftUser, $provider, $providerId);
        Craft::info("OAuth providers field updated for user ID={$craftUser->id}, provider={$provider}, providerId={$providerId}", 'oauth');

        // Step 4: Log in and redirect
        Craft::$app->getUser()->login($craftUser);
        Craft::info("User logged in: ID={$craftUser->id}", 'oauth');

        return $this->redirect(UrlHelper::siteUrl());
    }

    /**
     * Retrieves an existing user with the given email or creates a new user if one does not already exist.
     *
     * @param string $email The email address of the user.
     * @param string|null $name The name of the user.
     * @return User The retrieved or newly created user.
     * @throws \RuntimeException If the user cannot be created.
    */
    protected function findOrCreateUser(string $email, ?string $name): User
    {
        $user = Craft::$app->users->getUserByUsernameOrEmail($email);

        if ($user) {
            Craft::info("User found with email: {$email}", 'oauth');
            return $user;
        }

        $user = new User();
        $user->username = $email;
        $user->email = $email;
        $user->firstName = $name ?? '';
        $user->newPassword = null;
        $user->pending = false;
        $user->active = true;

        if (!Craft::$app->elements->saveElement($user)) {
            Craft::error("Failed to create user with email: {$email}", 'oauth');
            throw new \RuntimeException('Failed to create user.');
        }

        Craft::info("User created with email: {$email}", 'oauth');
        return $user;
    }

    /**
     * Assigns a user to a specified user group.
     *
     * @param User $user The user to assign.
     * @param string $groupHandle The handle of the user group to assign the user to.
     * @return void
    */
    protected function assignUserToGroup(User $user, string $groupHandle): void
    {
        $group = Craft::$app->userGroups->getGroupByHandle($groupHandle);
        if (!$group) {
            Craft::warning("User group '{$groupHandle}' not found.", __METHOD__);
            return;
        }

        $existingGroups = Craft::$app->userGroups->getGroupsByUserId($user->id);
        $groupIds = array_map(fn ($g) => $g->id, $existingGroups);

        if (!in_array($group->id, $groupIds)) {
            $groupIds[] = $group->id;
            Craft::$app->getUsers()->assignUserToGroups($user->id, $groupIds);
        }
    }

    /**
     * Updates the user's OAuth providers field with the given provider and provider ID.
     *
     * @param User $user The user to update.
     * @param string $provider The name of the OAuth provider.
     * @param string|int $providerId The ID of the user on the OAuth provider's platform.
     * @throws \Throwable
    */
    protected function updateOauthProvidersField(User $user, string $provider, string|int $providerId): void
    {
        $fieldHandle = 'oauthProviders';
        $existingProviders = $user->getFieldValue($fieldHandle) ?? [];

        $alreadyLinked = false;
        foreach ($existingProviders as $row) {
            if (
                (string) $row['provider'] === (string) $provider &&
                (string) $row['providerId'] === (string) $providerId
            ) {
                $alreadyLinked = true;
                break;
            }
        }

        if (!$alreadyLinked) {
            $existingProviders[] = [
                'provider' => $provider,
                'providerId' => $providerId,
            ];

            $user->setFieldValue($fieldHandle, $existingProviders);
            Craft::$app->elements->saveElement($user);
        }
    }
}
