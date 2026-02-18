<?php

namespace App\Core\Authentication\OAuth\Models;

use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\RandomString;
use League\OAuth2\Server\Entities\ClientEntityInterface;

/**
 * @property string   $identifier
 * @property string   $name
 * @property string   $secret
 * @property array    $redirect_uris
 * @property int|null $tenant_id
 */
class OAuthApplication extends Model implements ClientEntityInterface
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                validate: ['unique', 'column' => 'name'],
            ),
            'identifier' => new Property(),
            'secret' => new Property(
                encrypted: true,
                in_array: false,
            ),
            'redirect_uris' => new Property(
                type: Type::ARRAY,
            ),
            'tenant_id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
            ),
        ];
    }

    public function isConfidential(): bool
    {
        return true;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRedirectUri(): array
    {
        return $this->redirect_uris;
    }

    /**
     * @throws ModelException
     */
    public static function makeNewApp(string $name, array $redirectUris = [], ?int $tenantId = null): self
    {
        $application = new OAuthApplication();
        $application->name = $name;
        $application->redirect_uris = $redirectUris;
        $application->tenant_id = $tenantId;
        $application->identifier = RandomString::generate(24, RandomString::CHAR_ALNUM);
        $application->secret = RandomString::generate(32, RandomString::CHAR_ALNUM);
        $application->saveOrFail();

        return $application;
    }
}
