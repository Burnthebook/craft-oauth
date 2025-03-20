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
        $oauthService = OAuth::getInstance()->oauthService;
        $result = $oauthService->handleCallback($provider);

        if (!$result) {
            return $this->asJson(['error' => 'OAuth login failed']);
        }

        $oauthUser = $result['user'];
        $email = $oauthUser->getEmail();
        $providerId = $oauthUser->getId();
        $name = $oauthUser->getName();

        if (!$email) {
            return $this->asJson(['error' => 'No email returned from provider']);
        }

        // Step 1: Get or create the user
        $craftUser = $this->getOrCreateUser($email, $name);

        // Step 2: Assign to OAuth Users group
        $this->assignUserToGroup($craftUser, 'oauthUsers');

        // Step 3: Update oauthProviders field
        $this->updateOauthProvidersField($craftUser, $provider, $providerId);

        // Step 4: Log in and redirect
        Craft::$app->getUser()->login($craftUser);
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
    protected function getOrCreateUser(string $email, ?string $name): User
    {
        $user = Craft::$app->users->getUserByUsernameOrEmail($email);

        if ($user) {
            return $user;
        }

        $user = new User();
        $user->username = $email;
        $user->email = $email;
        $user->firstName = $name ?? '';
        $user->newPassword = null;

        if (!Craft::$app->elements->saveElement($user)) {
            throw new \RuntimeException('Failed to create user.');
        }

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
