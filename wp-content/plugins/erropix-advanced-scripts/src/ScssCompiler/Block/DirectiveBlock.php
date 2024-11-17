<?php

/**
 * SCSSPHP
 *
 * @copyright 2012-2020 Leaf Corcoran
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @link http://scssphp.github.io/scssphp
 */

namespace ERROPiX\AdvancedScripts\ScssCompiler\Block;

use ERROPiX\AdvancedScripts\ScssCompiler\Block;
use ERROPiX\AdvancedScripts\ScssCompiler\Type;

/**
 * @internal
 */
class DirectiveBlock extends Block
{
    /**
     * @var string|array
     */
    public $name;

    /**
     * @var string|array|null
     */
    public $value;

    public function __construct()
    {
        $this->type = Type::T_DIRECTIVE;
    }
}
