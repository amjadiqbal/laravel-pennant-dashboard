<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Support;

use AmjadIqbal\PennantDashboard\Concerns\HasRollout;
use AmjadIqbal\PennantDashboard\Models\Override;
use AmjadIqbal\PennantDashboard\Models\Rule;
use Closure;
use Illuminate\Support\Lottery;
use ReflectionClass;
use ReflectionFunction;
use Throwable;

/**
 * Every feature the dashboard can show for one store: defined in code, stored by the driver,
 * or touched from the dashboard.
 */
class FeatureCatalog
{
    /** @var array<int, true> decorators already discovered, by object id */
    private static array $discovered = [];

    public function __construct(private readonly StoreInspector $store) {}

    /** Define class-based features from app/Features, the way Feature::discover() does. */
    public function discover(): void
    {
        $id = spl_object_id($this->store->decorator);

        if (isset(self::$discovered[$id]) || ! config('pennant-dashboard.discover.enabled', true)) {
            return;
        }

        self::$discovered[$id] = true;
        $path = config('pennant-dashboard.discover.path') ?? app_path('Features');

        if (is_dir((string) $path)) {
            $this->store->decorator->discover((string) config('pennant-dashboard.discover.namespace', 'App\\Features'), (string) $path);
        }
    }

    public static function resetDiscovery(): void
    {
        self::$discovered = [];
    }

    /** @return array<string, FeatureInfo> keyed by feature name, sorted */
    public function all(): array
    {
        $this->discover();

        $defined = array_map('strval', $this->store->decorator->defined());
        $stored = $this->store->storedFeatureNames();
        $rules = $this->safe(fn () => Rule::query()->pluck('percentage', 'feature')->all(), []);
        $overrides = $this->safe(fn () => Override::query()->selectRaw('feature, count(*) as aggregate')->groupBy('feature')->pluck('aggregate', 'feature')->all(), []);
        $counts = $this->store->counts();

        $names = array_unique([...$defined, ...$stored, ...array_keys($rules), ...array_keys($overrides)]);
        sort($names);

        $features = [];
        foreach ($names as $name) {
            $name = (string) $name;
            $features[$name] = $this->describe(
                $name,
                in_array($name, $defined, true),
                in_array($name, $stored, true) || isset($counts[$name]),
                isset($rules[$name]) ? (int) $rules[$name] : null,
                (int) ($overrides[$name] ?? 0),
                $counts[$name] ?? null,
            );
        }

        return $features;
    }

    public function find(string $name): ?FeatureInfo
    {
        return $this->all()[$name] ?? null;
    }

    /** @param array{total: int, on: int, off: int, other: int}|null $counts */
    private function describe(string $name, bool $defined, bool $stored, ?int $percentage, int $overrides, ?array $counts): FeatureInfo
    {
        $resolver = $this->store->decorator->nameMap()[$name] ?? null;
        [$kind, $class] = match (true) {
            ! $defined => ['not defined', null],
            is_string($resolver) && class_exists($resolver) => ['class', $resolver],
            $resolver instanceof Lottery => ['lottery', null],
            $resolver instanceof Closure => ['closure', null],
            default => ['value', null],
        };

        return new FeatureInfo(
            name: $name,
            defined: $defined,
            stored: $stored,
            kind: $kind,
            class: $class,
            managed: $this->usesRollout($resolver),
            percentage: $percentage,
            overrides: $overrides,
            counts: $counts,
        );
    }

    /** Does the resolver call Rollout::decide() (or use the HasRollout trait)? Read from its source. */
    private function usesRollout(mixed $resolver): bool
    {
        try {
            if (is_string($resolver) && class_exists($resolver)) {
                if (in_array(HasRollout::class, class_uses_recursive($resolver), true)) {
                    return true;
                }
                $reflection = new ReflectionClass($resolver);
                $file = $reflection->getFileName();
                $from = $reflection->getStartLine();
                $to = $reflection->getEndLine();
            } elseif ($resolver instanceof Closure) {
                $reflection = new ReflectionFunction($resolver);
                $file = $reflection->getFileName();
                $from = $reflection->getStartLine();
                $to = $reflection->getEndLine();
            } else {
                return false;
            }

            if ($file === false || $from === false || $to === false || ! is_file($file)) {
                return false;
            }

            $source = implode('', array_slice(file($file) ?: [], $from - 1, $to - $from + 1));

            return str_contains($source, 'Rollout::decide(');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @param  T  $fallback
     * @return T
     */
    private function safe(Closure $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
