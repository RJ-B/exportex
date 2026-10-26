<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

/**
 * Podvržený nebo poškozený požadavek na Livewire ze skenerů z internetu.
 *
 * Skenery (Google Cloud a spol., útoky typu CVE-2025-54068) posílají na
 * `POST /livewire-…/update` upravené snapshoty: přepisují zamčené vlastnosti,
 * podstrkují nesmyslné hodnoty, volají neveřejné metody. Livewire je odmítne
 * výjimkou – obrana funguje, ale bez téhle třídy by to byla chyba 500
 * v Logy → Chyby, v laravel.log a v portálu jako incident.
 *
 * bootstrap/app.php takovou výjimku převede na 400 (BadRequestHttpException):
 * ErrorLogger chyby pod 500 nezapisuje a report callback je do laravel.log
 * nepustí. Podmínka je úzká – adresa update Livewire a zároveň výjimka, kterou
 * Livewire hází na upravený požadavek, nebo jakákoli výjimka při převodu
 * hodnoty poslané klientem (HandleSynths::hydratePropertyUpdate, zámky).
 * MethodNotFound / PublicPropertyNotFound sem schválně nepatří: stejně se
 * projeví překlep ve wire:click nebo wire:model a to je chyba v kódu.
 * Totéž filtruje portál pro starší projekty (ChybyAplikace::jePodvrzenyLivewire).
 */
class PodvrzenyLivewire
{
    /** Výjimky, kterými Livewire odmítá upravený požadavek. */
    public const VYJIMKY = [
        'CannotUpdateLockedPropertyException',
        'CorruptComponentPayloadException',
        'NonPublicComponentMethodCall',
        'DirectlyCallingLifecycleHooksNotAllowedException',
        'CannotCallComputedDirectlyException',
        'CannotMutateReactivePropException',
        'PayloadTooLargeException',
        'TooManyCallsException',
        'TooManyComponentsException',
        'MaxNestingDepthExceededException',
    ];

    public static function je(Throwable $e, ?Request $request = null): bool
    {
        $request ??= app()->bound('request') ? app('request') : null;

        if (! $request || ! $request->isMethod('POST') || ! preg_match('#(^|/)livewire(-[A-Za-z0-9]+)?/update$#', $request->path())) {
            return false;
        }

        if (in_array(class_basename($e), self::VYJIMKY, true)) {
            return true;
        }

        foreach ($e->getTrace() as $ramec) {
            if (($ramec['function'] ?? null) === 'hydratePropertyUpdate'
                || str_contains((string) ($ramec['class'] ?? ''), 'SupportLockedProperties')) {
                return true;
            }
        }

        return false;
    }

    /** Pro `$exceptions->map()`: podvržený požadavek → 400, ostatní beze změny. */
    public static function mapuj(Throwable $e): Throwable
    {
        return static::je($e)
            ? new BadRequestHttpException(__('obecne.podvrzeny_livewire.neplatny_pozadavek_livewire'), $e)
            : $e;
    }
}
