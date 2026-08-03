<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Exception\GenerationException;
use ReflectionClass;
use Throwable;

/**
 * Replaceable generation decisions of the PlatoPHP adapter.
 *
 * Every strategy is already a constructor parameter of PlatoControllerGenerator; what was missing
 * was a way to reach them without writing the wiring by hand. A project states `role => class` in
 * its configuration file and keeps the rest of the adapter, which is the point: an application whose
 * refusals carry its own envelope replaces the response writer alone, not the generator.
 *
 * The classes belong to the application being generated for, so they are checked before anything is
 * generated rather than fataling somewhere inside a template.
 */
final class PlatoStrategies
{
    /**
     * Role names as a configuration file spells them, and the interface each one has to satisfy.
     *
     * @var array<string, class-string>
     */
    public const ROLES = [
        'request-source' => RequestSource::class,
        'validation-rules' => ValidationRules::class,
        'dto-hydration' => DtoHydration::class,
        'logic-resolver' => LogicResolver::class,
        'response-writer' => ResponseWriter::class,
    ];

    private function __construct()
    {
    }

    /**
     * A controller generator using the project's strategies where it named one, the defaults where
     * it did not.
     *
     * @param array<string, string> $classes role => class name
     *
     * @throws GenerationException when a named class cannot serve the role
     */
    public static function generator(array $classes): PlatoControllerGenerator
    {
        $instances = self::instances($classes);

        // Every instance was checked against its interface before it was built; the instanceof here
        // is what says so in a way a static analyser can follow, and the default beside it is what
        // a role the project did not name keeps
        $requests = $instances['request-source'] ?? null;
        $validation = $instances['validation-rules'] ?? null;
        $hydration = $instances['dto-hydration'] ?? null;
        $logic = $instances['logic-resolver'] ?? null;
        $responses = $instances['response-writer'] ?? null;

        return new PlatoControllerGenerator(
            requests: $requests instanceof RequestSource ? $requests : new PlatoRequestSource(),
            validation: $validation instanceof ValidationRules ? $validation : new PlatoValidationRules(),
            hydration: $hydration instanceof DtoHydration ? $hydration : new PlatoDtoHydration(),
            logic: $logic instanceof LogicResolver ? $logic : new PlatoLogicResolver(),
            responses: $responses instanceof ResponseWriter ? $responses : new PlatoResponseWriter(),
        );
    }

    /**
     * @param array<string, string> $classes
     *
     * @return array<string, object>
     *
     * @throws GenerationException
     */
    private static function instances(array $classes): array
    {
        $errors = [];
        $instances = [];

        foreach ($classes as $role => $class) {
            $interface = self::ROLES[$role] ?? null;
            if ($interface === null) {
                $errors[] = 'Unknown strategy role ' . $role . '. Known roles: '
                    . implode(', ', array_keys(self::ROLES)) . '.';
                continue;
            }

            $error = self::error($class, $interface);
            if ($error !== null) {
                $errors[] = 'Strategy ' . $role . ': ' . $error;
                continue;
            }

            try {
                $instances[$role] = new $class();
            } catch (Throwable $exception) {
                // A constructor that refuses its own defaults is the project's bug, but it has to
                // be reported as a configuration problem rather than as an internal failure
                $errors[] = 'Strategy ' . $role . ': ' . $class . ' could not be constructed: '
                    . $exception->getMessage();
            }
        }

        if ($errors !== []) {
            throw new GenerationException($errors);
        }

        return $instances;
    }

    /**
     * What is wrong with this class for this role, or null when nothing is.
     *
     * @param class-string $interface
     */
    private static function error(string $class, string $interface): ?string
    {
        if (!class_exists($class)) {
            return $class . ' does not exist. Autoloading is the project\'s, so check the class is '
                . 'reachable from the composer autoloader this command runs with.';
        }

        if (!is_subclass_of($class, $interface)) {
            return $class . ' does not implement ' . $interface . '.';
        }

        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            return $class . ' cannot be instantiated.';
        }

        $constructor = $reflection->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            return $class . ' must be constructible without arguments; give its dependencies '
                . 'defaults, since the generator has nothing to pass them.';
        }

        return null;
    }
}
