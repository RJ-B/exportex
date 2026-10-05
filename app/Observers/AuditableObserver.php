<?php

namespace App\Observers;

use App\Models\Nastaveni;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Zapisuje do Aktivity změny sledovaných modelů.
 *
 * Přes observer, ne ručně v kontrolerech: administrace stojí na Filamentu,
 * který si zápisy dělá sám. Zachytí se tak i změny z konzole.
 *
 * KLÍČOVÉ je, co se NEsleduje. Kdyby se logovalo každé uložení, zaplaví
 * audit provozní šum (časy přihlášení, počítadla) a to podstatné – kdo
 * změnil nastavení, kdo komu dal práva – v tom zanikne. Nový model, u kterého
 * se má vědět „kdo to změnil“, přidej do WATCHED a do AppServiceProvider.
 */
class AuditableObserver
{
    /**
     * Sledovaná pole podle modelu. Co tu není, se neloguje.
     *
     * @var array<class-string, list<string>>
     */
    public const WATCHED = [
        User::class => ['email', 'jmeno', 'prijmeni', 'role'],
        Nastaveni::class => ['klic', 'hodnota'],
    ];

    public function created(Model $model): void
    {
        $this->write($model, 'created', null, $this->watched($model, $model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changed = $this->watched($model, $model->getChanges());

        // Změnilo se jen něco nesledovaného – mlčíme.
        if ($changed === []) {
            return;
        }

        // Přes watched(), aby i původní hodnota prošla skrytím hesel.
        $before = $this->watched($model, array_intersect_key($model->getOriginal(), $changed));

        $this->write($model, 'updated', $before, $changed);
    }

    public function deleted(Model $model): void
    {
        $this->write($model, 'deleted', $this->watched($model, $model->getAttributes()), null);
    }

    /** @param array<string, mixed> $attributes */
    private function watched(Model $model, array $attributes): array
    {
        $keys = self::WATCHED[$model::class] ?? [];
        $sledovane = array_intersect_key($attributes, array_flip($keys));

        // Hesla, tokeny a tajemství (i zašifrované) do Aktivity nepatří – jen že se změnily.
        if ($model instanceof Nastaveni && preg_match('/\.(heslo|token|webhook_tajemstvi(_predchozi)?)$/', (string) $model->getAttribute('klic')) && array_key_exists('hodnota', $sledovane)) {
            $sledovane['hodnota'] = $sledovane['hodnota'] === null ? null : '(heslo skryto)';
        }

        return $sledovane;
    }

    private function write(Model $model, string $action, ?array $old, ?array $new): void
    {
        AuditLogger::record(
            event: strtolower(class_basename($model)).'.'.$action,
            subject: $model,
            summary: $this->describe($model, $action),
            old: $old,
            new: $new,
        );
    }

    private function describe(Model $model, string $action): string
    {
        $label = match ($action) {
            'created' => 'Vytvořeno',
            'deleted' => 'Smazáno',
            default => 'Změněno',
        };

        $nazev = $model instanceof User
            ? $model->getFilamentName()
            : ($model->getAttribute('klic') ?? '#'.$model->getKey());

        return $label.': '.class_basename($model).' – '.$nazev;
    }
}
