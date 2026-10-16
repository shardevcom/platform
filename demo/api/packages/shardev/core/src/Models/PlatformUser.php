<?php

declare(strict_types=1);

namespace Shardevcom\Core\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * Modelo base abstracto para usuarios de plataforma (T-202).
 *
 * Extiende Authenticatable y añade traits comunes para usuarios de plataforma.
 * Cada plataforma debe extender esta clase y definir su tabla y guard.
 *
 * T-090: los tokens de acceso son personales de Passport (tablas oauth_*).
 * Implementar OAuthenticatable es obligatorio, no decorativo: Passport
 * descarta cualquier modelo que no cumpla el contrato antes de llegar a
 * withAccessToken(), así que un guard `passport` devolvería null y devolvería
 * 401 aunque el token fuera válido.
 */
abstract class PlatformUser extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * El guard de autenticación que usa este modelo.
     * Debe ser sobrescrito en la clase hija.
     *
     * @var string
     */
    protected static string $platformGuard = 'web';

    /**
     * Obtener el guard de autenticación para este modelo.
     */
    public function getGuard(): string
    {
        return static::$platformGuard;
    }

    /**
     * Determinar si el usuario puede autenticarse con el guard dado.
     */
    public function canAuthenticateWith(string $guard): bool
    {
        return $guard === static::$platformGuard;
    }

    /**
     * Scope para consultas que filtra por guard.
     */
    public function scopeForGuard($query, string $guard)
    {
        return $query->where(fn ($q) => $guard === static::$platformGuard ? $q : $q->whereRaw('1=0'));
    }
}