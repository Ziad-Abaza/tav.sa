<?php

namespace App\Table;

class Column
{
    protected string $attribute;

    protected string $label = '';

    protected bool $sortable = false;

    protected bool $primary = false;

    protected bool $hidden = false;

    protected ?string $component = null;

    protected ?string $width = null;

    protected ?string $class = null;

    protected array $meta = [];

    public static function make(string $attribute): static
    {
        $column = new static;
        $column->attribute = $attribute;

        return $column;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function sortable(bool $value = true): static
    {
        $this->sortable = $value;

        return $this;
    }

    /**
     * Mark column as primary (always visible, not toggleable by user).
     */
    public function primary(bool $value = true): static
    {
        $this->primary = $value;

        return $this;
    }

    /**
     * Column is hidden by default on first visit (before user customises visibility).
     */
    public function hidden(bool $value = true): static
    {
        $this->hidden = $value;

        return $this;
    }

    /**
     * Name of a registered Vue cell component (e.g. 'BadgeCell', 'DateCell').
     * Slot override in the parent component always wins over this.
     */
    public function component(string $name): static
    {
        $this->component = $name;

        return $this;
    }

    /**
     * CSS width hint, e.g. '200px'. Converted to Tailwind w-[…] in Vue.
     */
    public function width(string $width): static
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Extra Tailwind classes applied to <th>/<td>.
     */
    public function class(string $class): static
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Arbitrary metadata passed to the cell component (e.g. urlPath, urlKey).
     */
    public function meta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->attribute,
            'label' => $this->label,
            'sortable' => $this->sortable,
            'primary' => $this->primary,
            'hidden' => $this->hidden,
            'component' => $this->component,
            'width' => $this->width,
            'class' => $this->class,
            'meta' => $this->meta ?: null,
        ];
    }
}
