<?php

declare(strict_types=1);

namespace Sg\DatatablesBundle\Datatable\Column;

trait EscapingTrait
{
    protected $escape = true;

    public function isEscape(): bool
    {
        return $this->escape;
    }

    /**
     * @param bool $escape
     * @return $this
     */
    public function setEscape(bool $escape)
    {
        $this->escape = $escape;

        return $this;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected function escapeValue($value)
    {
        if (is_array($value)) {
            return array_map('self::escapeValue', $value);
        }
        if ($value !== null) {
            return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return $value;
    }
}
