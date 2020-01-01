<?php

namespace LaravelEnso\Forms\App\Services\Validators;

use Illuminate\Support\Collection;
use LaravelEnso\Forms\App\Attributes\Structure as Attributes;
use LaravelEnso\Forms\App\Exceptions\Template;
use LaravelEnso\Helpers\App\Classes\Obj;

class Structure
{
    private Obj $template;

    public function __construct(Obj $template)
    {
        $this->template = $template;
    }

    public function validate()
    {
        $this->rootMandatoryAttributes()
            ->rootOptionalAttributes()
            ->rootAttributesFormat()
            ->sections()
            ->tabs();
    }

    private function rootMandatoryAttributes(): self
    {
        $diff = (new Collection(Attributes::Mandatory))
            ->diff($this->template->keys());

        if ($diff->isNotEmpty()) {
            throw Template::missingRootAttributes($diff->implode('", "'));
        }

        return $this;
    }

    private function rootOptionalAttributes(): self
    {
        $attributes = (new Collection(Attributes::Mandatory))
            ->merge(Attributes::Optional);

        $diff = $this->template->keys()
            ->diff($attributes);

        if ($diff->isNotEmpty()) {
            throw Template::unknownRootAttributes($diff->implode('", "'));
        }

        return $this;
    }

    private function rootAttributesFormat(): self
    {
        if ($this->template->has('actions')
            && ! $this->template->get('actions') instanceof Obj) {
            throw Template::invalidActionsFormat();
        }

        if ($this->template->has('params')
            && ! $this->template->get('params') instanceof Obj) {
            throw Template::invalidParamsFormat();
        }

        if (! $this->template->get('sections') instanceof Obj) {
            throw Template::invalidSectionFormat();
        }

        return $this;
    }

    private function sections(): self
    {
        $attributes = $this->template->get('sections')
            ->reduce(fn ($attributes, $section) => $attributes
                ->merge($section->keys()), new Collection()
            )->unique()->values();

        $this->sectionsMandatory($attributes)
            ->sectionsOptional($attributes)
            ->sectionsColumnFormat();

        return $this;
    }

    private function sectionsMandatory(Collection $attributes): self
    {
        $diff = (new Collection(Attributes::SectionMandatory))
            ->diff($attributes);

        if ($diff->isNotEmpty()) {
            throw Template::missingSectionAttributes($diff->implode('", "'));
        }

        return $this;
    }

    private function sectionsOptional(Collection $attributes): self
    {
        $diff = $attributes->diff(
            (new Collection(Attributes::SectionMandatory))
                ->merge(Attributes::SectionOptional)
        );

        if ($diff->isNotEmpty()) {
            throw Template::unknownSectionAttributes($diff->implode('", "'));
        }

        return $this;
    }

    private function sectionsColumnFormat(): void
    {
        $this->template->get('sections')
            ->each(fn ($section) => $this->sectionColumnsFormat($section));
    }

    private function sectionColumnsFormat(Obj $section): void
    {
        $attributes = (new Collection(Attributes::Columns));

        if (! $attributes->contains($section->get('columns'))) {
            throw Template::invalidColumnsAttributes(
                $section->get('columns'), $attributes->implode(', ')
            );
        }

        if ($section->get('columns') === 'custom') {
            $section->get('fields')
                ->each(fn ($field) => $this->checkCustomColumns($field));
        }
    }

    private function checkCustomColumns(Obj $field): void
    {
        if (! $field->has('column')) {
            throw Template::missingFieldColumn($field->get('name'));
        }

        if (! is_int($field->get('column'))
            || $field->get('column') <= 0
            || $field->get('column') > 12) {
            throw Template::invalidFieldColumn($field->get('name'));
        }
    }

    private function tabs(): void
    {
        if (! $this->template->get('tabs')) {
            return;
        }

        $diff = $this->template->get('sections')
            ->filter(fn ($section) => ! $section->has('tab'));

        if ($diff->isNotEmpty()) {
            throw Template::missingTabAttribute($diff->keys()->implode('", "'));
        }
    }
}
