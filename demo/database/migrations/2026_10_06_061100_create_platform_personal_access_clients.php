<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * One personal access client per platform.
 *
 * Passport looks the client up by the user provider of the model minting the
 * token, and refuses to issue one when it cannot find a client for that
 * provider. Since every platform here is its own provider, one row for the
 * whole application is not enough: the second platform would fail at
 * createToken() with "Personal access client not found".
 *
 * Built through Passport's own repository rather than inserted by hand
 * because which columns exist (grant_types versus personal_access_client)
 * changed between Passport versions and the repository already knows.
 */
return new class extends Migration
{
    public function up(): void
    {
        $repository = app(ClientRepository::class);

        foreach (config('platforms.platforms', []) as $platform) {
            $repository->createPersonalAccessGrantClient(
                'Shardev '.$platform['key'].' personal access',
                $platform['provider'],
            );
        }
    }

    public function down(): void
    {
        Passport::client()
            ->newQuery()
            ->where('name', 'like', 'Shardev % personal access')
            ->delete();
    }
};
