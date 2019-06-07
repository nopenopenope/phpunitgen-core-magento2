<?php

declare(strict_types=1);

namespace PhpUnitGen\Core\Contracts;

/**
 * Interface Source.
 *
 * An object which contains the source to parse for CodeParser.
 *
 * @package PhpUnitGen\Core
 * @author  Paul Thébaud <paul.thebaud29@gmail.com>
 * @author  Killian Hascoët <killianh@live.fr>
 * @license MIT
 */
interface Source
{
    /**
     * Get the source code as a string.
     *
     * @return string
     */
    public function toString(): string;
}
