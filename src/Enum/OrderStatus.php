<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::CONFIRMED => 'Confirmee',
            self::SHIPPED => 'Expadie',
            self::DELIVERED => 'Livr',
            self::CANCELLED => 'Annulee',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::DELIVERED, self::CANCELLED => true,
            default => false,
        };
    }

    public static function isValid(string $value): bool
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return true;
            }
        }
        return false;
    }
}