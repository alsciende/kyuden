<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Attribute\Description;
use App\Service\ActionMessageRegistry;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function Symfony\Component\String\u;

/**
 * Builds the JSON schema (draft 2020-12) of the payload of the action messages players send through the API, from
 * their constructor (see ApiMessageFactory): the types of the arguments (with their PHPDoc), their constraints
 * (Validator) and their description (Description attribute); the message is described by its class doc comment, in
 * which the fields quoted with backticks are named as in the payload.
 *
 * A type or a constraint the factory cannot translate throws a LogicException, so that the schema never accepts more
 * than the engine does.
 */
readonly class MessageSchemaFactory
{
    public const string DIALECT = 'https://json-schema.org/draft/2020-12/schema';

    public function __construct(
        private ActionMessageRegistry $actionMessageRegistry,
        private TypeResolverInterface $typeResolver,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>> the schemas of the public messages, by type
     */
    public function createAll(): array
    {
        $schemas = [];
        foreach ($this->actionMessageRegistry->getPublicMessageClasses() as $type => $className) {
            $schemas[$type] = $this->create($type, $className);
        }

        return $schemas;
    }

    /**
     * The schema as stored in the snapshot (app:message-schemas:dump) and served by the catalogue.
     *
     * @param array<string, mixed> $schema
     */
    public static function encode(array $schema): string
    {
        return json_encode($schema, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @param class-string $className
     *
     * @return array<string, mixed>
     */
    public function create(string $type, string $className): array
    {
        $class = new \ReflectionClass($className);
        $metadata = $this->validator->getMetadataFor($className);
        if (! $metadata instanceof ClassMetadataInterface) {
            throw new \LogicException("No validation metadata for {$className}.");
        }

        $properties = [];
        $required = [];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $name = u($parameter->getName())->snake()->toString();
            if (in_array($name, [ApiMessageFactory::GAME_ID, ApiMessageFactory::PLAYER_ID], true)) {
                continue;
            }

            $properties[$name] = $this->createProperty($parameter, $metadata, $className);
            if (! $parameter->isDefaultValueAvailable()) {
                $required[] = $name;
            }
        }

        return array_filter([
            '$schema' => self::DIALECT,
            'title' => $type,
            'description' => $this->getDescription($class),
            'type' => 'object',
            // an empty object, not an empty list, in JSON
            'properties' => (object) $properties,
            'required' => $required,
            'additionalProperties' => false,
        ], fn (mixed $value): bool => $value !== [] && $value !== null);
    }

    /**
     * @param class-string $className
     *
     * @return array<string, mixed>
     */
    private function createProperty(\ReflectionParameter $parameter, ClassMetadataInterface $metadata, string $className): array
    {
        $where = "{$className}::\${$parameter->getName()}";
        $property = $this->createType($this->typeResolver->resolve($parameter), $where);

        $description = ($parameter->getAttributes(Description::class)[0] ?? null)?->newInstance()->description;
        if ($description !== null) {
            $property = ['description' => $description, ...$property];
        }

        if ($metadata->hasPropertyMetadata($parameter->getName())) {
            foreach ($metadata->getPropertyMetadata($parameter->getName()) as $propertyMetadata) {
                foreach ($propertyMetadata->getConstraints() as $constraint) {
                    $property = [...$property, ...$this->createConstraint($constraint, $where)];
                }
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            $default = $parameter->getDefaultValue();
            $property['default'] = $default instanceof \BackedEnum ? $default->value : $default;
        }

        return $property;
    }

    /**
     * @return array<string, mixed>
     */
    private function createType(Type $type, string $where): array
    {
        return match (true) {
            // a nullable scalar, e.g. ?string: ['string', 'null']
            $type instanceof NullableType && $type->getWrappedType() instanceof BuiltinType => [
                'type' => [$this->createType($type->getWrappedType(), $where)['type'], 'null'],
            ],
            $type instanceof BackedEnumType => [
                'type' => $type->getBackingType()->getTypeIdentifier() === TypeIdentifier::INT ? 'integer' : 'string',
                'enum' => $this->getEnumValues($type->getClassName()),
            ],
            $type instanceof CollectionType && $type->isList() => [
                'type' => 'array',
                'items' => $this->createType($type->getCollectionValueType(), $where),
            ],
            $type instanceof BuiltinType => ['type' => match ($type->getTypeIdentifier()) {
                TypeIdentifier::STRING => 'string',
                TypeIdentifier::INT => 'integer',
                TypeIdentifier::BOOL => 'boolean',
                default => throw new \LogicException("Type {$type} of {$where} has no JSON schema."),
            }],
            default => throw new \LogicException("Type {$type} of {$where} has no JSON schema."),
        };
    }

    /**
     * @return list<int|string>
     */
    private function getEnumValues(string $enumClass): array
    {
        if (! is_subclass_of($enumClass, \BackedEnum::class)) {
            throw new \LogicException("{$enumClass} is not a backed enum.");
        }

        return array_map(fn (\BackedEnum $case): int|string => $case->value, $enumClass::cases());
    }

    /**
     * @return array<string, mixed>
     */
    private function createConstraint(Constraint $constraint, string $where): array
    {
        return match (true) {
            $constraint instanceof GreaterThanOrEqual && is_int($constraint->value) => ['minimum' => $constraint->value],
            $constraint instanceof GreaterThan && is_int($constraint->value) => ['exclusiveMinimum' => $constraint->value],
            $constraint instanceof LessThanOrEqual && is_int($constraint->value) => ['maximum' => $constraint->value],
            $constraint instanceof LessThan && is_int($constraint->value) => ['exclusiveMaximum' => $constraint->value],
            $constraint instanceof Range && (is_int($constraint->min) || is_int($constraint->max)) => array_filter(
                ['minimum' => $constraint->min, 'maximum' => $constraint->max],
                is_int(...),
            ),
            $constraint instanceof Count => array_filter(['minItems' => $constraint->min, 'maxItems' => $constraint->max], is_int(...)),
            $constraint instanceof Length => array_filter(['minLength' => $constraint->min, 'maxLength' => $constraint->max], is_int(...)),
            default => throw new \LogicException(sprintf('Constraint %s of %s has no JSON schema.', $constraint::class, $where)),
        };
    }

    /**
     * The class doc comment, without its tags: lines joined into paragraphs.
     *
     * @param \ReflectionClass<object> $class
     */
    private function getDescription(\ReflectionClass $class): ?string
    {
        $docComment = $class->getDocComment();
        if ($docComment === false) {
            return null;
        }

        $lines = array_map(
            fn (string $line): string => trim((string) preg_replace('#^\s*(/\*\*|\*/|\*)\s?#', '', $line)),
            explode("\n", $docComment),
        );
        $text = trim(implode("\n", array_filter($lines, fn (string $line): bool => ! str_starts_with($line, '@'))));
        $paragraphs = array_map(
            fn (string $paragraph): string => (string) preg_replace('/\s*\n\s*/', ' ', trim($paragraph)),
            preg_split('/\n\s*\n/', $text) ?: [],
        );

        // the fields quoted in the comment are named as in the payload
        $description = (string) preg_replace_callback('/`(\w+)`/', fn (array $match): string => '`' . u($match[1])->snake()->toString() . '`', implode("\n\n", $paragraphs));

        return $description !== '' ? $description : null;
    }
}
