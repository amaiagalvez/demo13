<?php

namespace App\Support\Validation;

use Stringable;
use LogicException;
use Illuminate\Support\Str;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationRuleParser;

/**
 * The validations a FormRequest applies to one field, written out as the sentences the info icon
 * next to the label of that field shows.
 *
 * The rules of the request are the only source: the length limits come from the rule parameters and
 * therefore from config/validation.php, and a rule added to a request reaches the form without
 * anyone having to remember to write its wording down. Only the rules somebody can act on are
 * spelled out. A plain required rule is left out as well, since the red asterisk of the label
 * already says it, and it is isRequired() that decides that asterisk. Neither are the type rules
 * (string, integer, boolean), the transport ones (nullable, sometimes), the date format or the
 * existence of the record a selector points at: the control itself already decides what can be
 * chosen or typed in. A rule that carries its own wording cannot be turned into a sentence here
 * either, and is left out rather than guessed at.
 *
 * The length hints are offered for text only, since that is what min and max count characters in,
 * and every text field of these forms also carries a string rule.
 */
final class FieldHints
{
    /**
     * @var array<string, array<int, mixed>>|null
     */
    private ?array $declaredRules = null;

    /**
     * @param  class-string<FormRequest>  $request
     * @param  array<string, string>  $labels  Translated labels of the other fields of the same form,
     *                                         so that a rule about one field names the other one the
     *                                         way the form does instead of naming its column.
     */
    public function __construct(
        private readonly string $request,
        private readonly array $labels = [],
    ) {}

    /**
     * Every validation of the field, in the order the request declares them.
     *
     * @return list<string>
     */
    public function for(string $field): array
    {
        $rules = $this->parsedRules($field);
        $text = in_array('string', array_column($rules, 0), true);

        $hints = [];

        foreach ($rules as [$name, $parameters]) {
            $hint = $this->hint($name, $parameters, $text);

            if ($hint !== null) {
                $hints[] = $hint;
            }
        }

        return $hints;
    }

    /**
     * Whether the field is asked for every time, which is what the red asterisk marks. A field
     * that is only asked for together with another one is not one of those.
     */
    public function isRequired(string $field): bool
    {
        return in_array('required', array_column($this->parsedRules($field), 0), true);
    }

    /**
     * @param  array<int, string>  $parameters
     */
    private function hint(string $name, array $parameters, bool $text): ?string
    {
        return match ($name) {
            'required_with' => $this->say('Required when :field is filled in.', [
                'field' => $this->label($parameters[0] ?? ''),
            ]),
            'min' => $text ? $this->say('Use at least :min characters.', ['min' => $parameters[0] ?? '']) : null,
            'max' => $text ? $this->say('Use at most :max characters.', ['max' => $parameters[0] ?? '']) : null,
            'after' => $this->say('Must be later than :field.', ['field' => $this->label($parameters[0] ?? '')]),
            'after_or_equal' => $this->say('Must be on or after :field.', [
                'field' => $this->label($parameters[0] ?? ''),
            ]),
            'unique' => $this->say('Must be unique.'),
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function say(string $notice, array $replace = []): string
    {
        return (string) __($notice, $replace);
    }

    /**
     * The rules of the field as the validator reads them: the name of each rule and its parameters.
     *
     * @return list<array{0: string, 1: array<int, string>}>
     */
    private function parsedRules(string $field): array
    {
        /** @var array<string, array<int, mixed>> $exploded */
        $exploded = (new ValidationRuleParser([]))->explode([
            $field => $this->declaredRules()[$field] ?? [],
        ])->rules;

        $parsed = [];

        foreach ($exploded[$field] ?? [] as $rule) {
            if (! is_string($rule) && ! $rule instanceof Stringable) {
                continue;
            }

            /** @var array{0: string, 1: array<int, string>} $read */
            $read = ValidationRuleParser::parse((string) $rule);

            $parsed[] = [Str::snake($read[0]), $read[1]];
        }

        return $parsed;
    }

    /**
     * The rules of the request, read without a route: a notice is about what the form asks for, and
     * the record being edited only decides which records the uniqueness check ignores.
     *
     * @return array<string, array<int, mixed>>
     */
    private function declaredRules(): array
    {
        if ($this->declaredRules !== null) {
            return $this->declaredRules;
        }

        $requestClass = $this->request;
        $request = new $requestClass;
        $request->setRouteResolver(static fn (): null => null);

        if (! method_exists($request, 'rules')) {
            throw new LogicException("{$requestClass} must declare rules() so its forms can read the validations.");
        }

        /** @var array<string, array<int, mixed>|string> $rules */
        $rules = $request->rules();

        /** @var array<string, array<int, mixed>> $declared */
        $declared = [];

        foreach ($rules as $attribute => $attributeRules) {
            $declared[$attribute] = (array) $attributeRules;
        }

        return $this->declaredRules = $declared;
    }

    /**
     * A rule that talks about another field names it the way the form labels it, since the column
     * name means nothing to whoever reads the notice.
     */
    private function label(string $field): string
    {
        $readable = Str::lower(str_replace('_', ' ', $field));

        return Str::lcfirst($this->labels[$field] ?? $readable);
    }
}
