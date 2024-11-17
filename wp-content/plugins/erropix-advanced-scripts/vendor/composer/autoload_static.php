<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit35e6f3ad324865b2b638cfc5ed3cc791
{
    public static $files = array (
        '06f5f32a6edcbd18661c24705435ceb1' => __DIR__ . '/..' . '/freemius/wordpress-sdk/start.php',
        '85571c4a3ee62b52f6ed9a76dd37c50c' => __DIR__ . '/../..' . '/src/functions.php',
    );

    public static $prefixLengthsPsr4 = array (
        'E' => 
        array (
            'ERROPiX\\AdvancedScripts\\' => 24,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'ERROPiX\\AdvancedScripts\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'L' => 
        array (
            'Less' => 
            array (
                0 => __DIR__ . '/..' . '/wikimedia/less.php/lib',
            ),
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'ERROPiX\\AdvancedScripts\\ConditionManager' => __DIR__ . '/../..' . '/src/ConditionManager.php',
        'ERROPiX\\AdvancedScripts\\FreemiusTweaker' => __DIR__ . '/../..' . '/src/FreemiusTweaker.php',
        'ERROPiX\\AdvancedScripts\\HooksWatcher' => __DIR__ . '/../..' . '/src/HooksWatcher.php',
        'ERROPiX\\AdvancedScripts\\HtmlUtils' => __DIR__ . '/../..' . '/src/HtmlUtils.php',
        'ERROPiX\\AdvancedScripts\\Migrations' => __DIR__ . '/../..' . '/src/Migrations.php',
        'ERROPiX\\AdvancedScripts\\Processor\\CSS' => __DIR__ . '/../..' . '/src/Processor/CSS.php',
        'ERROPiX\\AdvancedScripts\\Processor\\HTML' => __DIR__ . '/../..' . '/src/Processor/HTML.php',
        'ERROPiX\\AdvancedScripts\\Processor\\JavaScript' => __DIR__ . '/../..' . '/src/Processor/JavaScript.php',
        'ERROPiX\\AdvancedScripts\\Processor\\LESS' => __DIR__ . '/../..' . '/src/Processor/LESS.php',
        'ERROPiX\\AdvancedScripts\\Processor\\PHP' => __DIR__ . '/../..' . '/src/Processor/PHP.php',
        'ERROPiX\\AdvancedScripts\\Processor\\Processor' => __DIR__ . '/../..' . '/src/Processor/Processor.php',
        'ERROPiX\\AdvancedScripts\\Processor\\SCSS' => __DIR__ . '/../..' . '/src/Processor/SCSS.php',
        'ERROPiX\\AdvancedScripts\\ScriptsManager' => __DIR__ . '/../..' . '/src/ScriptsManager.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Base\\Range' => __DIR__ . '/../..' . '/src/ScssCompiler/Base/Range.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Block' => __DIR__ . '/../..' . '/src/ScssCompiler/Block.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Block\\AtRootBlock' => __DIR__ . '/../..' . '/src/ScssCompiler/Block/AtRootBlock.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Block\\CallableBlock' => __DIR__ . '/../..' . '/src/ScssCompiler/Block/CallableBlock.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Block\\ContentBlock' => __DIR__ . '/../..' . '/src/ScssCompiler/Block/ContentBlock.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Block\\DirectiveBlock' => __DIR__ . '/../..' . '/src/ScssCompiler/Block/DirectiveBlock.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Block\\EachBlock' => __DIR__ . '/../..' . '/src/ScssCompiler/Block/EachBlock.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Block\\ElseBlock' => __DIR__ . '/../..' . '/src/ScssCompiler/Block/ElseBlock.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Block\\ElseifBlock' => __DIR__ . '/../..' . '/src/ScssCompiler/Block/ElseifBlock.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Block\\ForBlock' => __DIR__ . '/../..' . '/src/ScssCompiler/Block/ForBlock.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Block\\IfBlock' => __DIR__ . '/../..' . '/src/ScssCompiler/Block/IfBlock.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Block\\MediaBlock' => __DIR__ . '/../..' . '/src/ScssCompiler/Block/MediaBlock.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Block\\NestedPropertyBlock' => __DIR__ . '/../..' . '/src/ScssCompiler/Block/NestedPropertyBlock.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Block\\WhileBlock' => __DIR__ . '/../..' . '/src/ScssCompiler/Block/WhileBlock.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Cache' => __DIR__ . '/../..' . '/src/ScssCompiler/Cache.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Colors' => __DIR__ . '/../..' . '/src/ScssCompiler/Colors.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\CompilationResult' => __DIR__ . '/../..' . '/src/ScssCompiler/CompilationResult.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Compiler' => __DIR__ . '/../..' . '/src/ScssCompiler/Compiler.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Compiler\\CachedResult' => __DIR__ . '/../..' . '/src/ScssCompiler/Compiler/CachedResult.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Compiler\\Environment' => __DIR__ . '/../..' . '/src/ScssCompiler/Compiler/Environment.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Exception\\CompilerException' => __DIR__ . '/../..' . '/src/ScssCompiler/Exception/CompilerException.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Exception\\ParserException' => __DIR__ . '/../..' . '/src/ScssCompiler/Exception/ParserException.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Exception\\RangeException' => __DIR__ . '/../..' . '/src/ScssCompiler/Exception/RangeException.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Exception\\SassException' => __DIR__ . '/../..' . '/src/ScssCompiler/Exception/SassException.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Exception\\SassScriptException' => __DIR__ . '/../..' . '/src/ScssCompiler/Exception/SassScriptException.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Exception\\ServerException' => __DIR__ . '/../..' . '/src/ScssCompiler/Exception/ServerException.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Formatter' => __DIR__ . '/../..' . '/src/ScssCompiler/Formatter.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Formatter\\Compact' => __DIR__ . '/../..' . '/src/ScssCompiler/Formatter/Compact.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Formatter\\Compressed' => __DIR__ . '/../..' . '/src/ScssCompiler/Formatter/Compressed.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Formatter\\Crunched' => __DIR__ . '/../..' . '/src/ScssCompiler/Formatter/Crunched.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Formatter\\Debug' => __DIR__ . '/../..' . '/src/ScssCompiler/Formatter/Debug.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Formatter\\Expanded' => __DIR__ . '/../..' . '/src/ScssCompiler/Formatter/Expanded.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Formatter\\Nested' => __DIR__ . '/../..' . '/src/ScssCompiler/Formatter/Nested.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Formatter\\OutputBlock' => __DIR__ . '/../..' . '/src/ScssCompiler/Formatter/OutputBlock.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Logger\\LoggerInterface' => __DIR__ . '/../..' . '/src/ScssCompiler/Logger/LoggerInterface.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Logger\\QuietLogger' => __DIR__ . '/../..' . '/src/ScssCompiler/Logger/QuietLogger.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Logger\\StreamLogger' => __DIR__ . '/../..' . '/src/ScssCompiler/Logger/StreamLogger.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Node' => __DIR__ . '/../..' . '/src/ScssCompiler/Node.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Node\\Number' => __DIR__ . '/../..' . '/src/ScssCompiler/Node/Number.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\OutputStyle' => __DIR__ . '/../..' . '/src/ScssCompiler/OutputStyle.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Parser' => __DIR__ . '/../..' . '/src/ScssCompiler/Parser.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\SourceMap\\Base64' => __DIR__ . '/../..' . '/src/ScssCompiler/SourceMap/Base64.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\SourceMap\\Base64VLQ' => __DIR__ . '/../..' . '/src/ScssCompiler/SourceMap/Base64VLQ.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\SourceMap\\SourceMapGenerator' => __DIR__ . '/../..' . '/src/ScssCompiler/SourceMap/SourceMapGenerator.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Type' => __DIR__ . '/../..' . '/src/ScssCompiler/Type.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Util' => __DIR__ . '/../..' . '/src/ScssCompiler/Util.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Util\\Path' => __DIR__ . '/../..' . '/src/ScssCompiler/Util/Path.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\ValueConverter' => __DIR__ . '/../..' . '/src/ScssCompiler/ValueConverter.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Version' => __DIR__ . '/../..' . '/src/ScssCompiler/Version.php',
        'ERROPiX\\AdvancedScripts\\ScssCompiler\\Warn' => __DIR__ . '/../..' . '/src/ScssCompiler/Warn.php',
        'ERROPiX\\AdvancedScripts\\SiteHealth' => __DIR__ . '/../..' . '/src/SiteHealth.php',
        'ERROPiX\\AdvancedScripts\\Storage' => __DIR__ . '/../..' . '/src/Storage.php',
        'ERROPiX\\AdvancedScripts\\Utils' => __DIR__ . '/../..' . '/src/Utils.php',
        'Less_Autoloader' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Autoloader.php',
        'Less_Cache' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Cache.php',
        'Less_Colors' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Colors.php',
        'Less_Configurable' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Configurable.php',
        'Less_Environment' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Environment.php',
        'Less_Exception_Chunk' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Exception/Chunk.php',
        'Less_Exception_Compiler' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Exception/Compiler.php',
        'Less_Exception_Parser' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Exception/Parser.php',
        'Less_Functions' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Functions.php',
        'Less_Mime' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Mime.php',
        'Less_Output' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Output.php',
        'Less_Output_Mapped' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Output/Mapped.php',
        'Less_Parser' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Parser.php',
        'Less_SourceMap_Base64VLQ' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/SourceMap/Base64VLQ.php',
        'Less_SourceMap_Generator' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/SourceMap/Generator.php',
        'Less_Tree' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree.php',
        'Less_Tree_Alpha' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Alpha.php',
        'Less_Tree_Anonymous' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Anonymous.php',
        'Less_Tree_Assignment' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Assignment.php',
        'Less_Tree_Attribute' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Attribute.php',
        'Less_Tree_Call' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Call.php',
        'Less_Tree_Color' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Color.php',
        'Less_Tree_Comment' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Comment.php',
        'Less_Tree_Condition' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Condition.php',
        'Less_Tree_DefaultFunc' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/DefaultFunc.php',
        'Less_Tree_DetachedRuleset' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/DetachedRuleset.php',
        'Less_Tree_Dimension' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Dimension.php',
        'Less_Tree_Directive' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Directive.php',
        'Less_Tree_Element' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Element.php',
        'Less_Tree_Expression' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Expression.php',
        'Less_Tree_Extend' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Extend.php',
        'Less_Tree_Import' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Import.php',
        'Less_Tree_Javascript' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Javascript.php',
        'Less_Tree_Keyword' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Keyword.php',
        'Less_Tree_Media' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Media.php',
        'Less_Tree_Mixin_Call' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Mixin/Call.php',
        'Less_Tree_Mixin_Definition' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Mixin/Definition.php',
        'Less_Tree_NameValue' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/NameValue.php',
        'Less_Tree_Negative' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Negative.php',
        'Less_Tree_Operation' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Operation.php',
        'Less_Tree_Paren' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Paren.php',
        'Less_Tree_Quoted' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Quoted.php',
        'Less_Tree_Rule' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Rule.php',
        'Less_Tree_Ruleset' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Ruleset.php',
        'Less_Tree_RulesetCall' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/RulesetCall.php',
        'Less_Tree_Selector' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Selector.php',
        'Less_Tree_UnicodeDescriptor' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/UnicodeDescriptor.php',
        'Less_Tree_Unit' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Unit.php',
        'Less_Tree_UnitConversions' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/UnitConversions.php',
        'Less_Tree_Url' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Url.php',
        'Less_Tree_Value' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Value.php',
        'Less_Tree_Variable' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Tree/Variable.php',
        'Less_Version' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Version.php',
        'Less_Visitor' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Visitor.php',
        'Less_VisitorReplacing' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/VisitorReplacing.php',
        'Less_Visitor_extendFinder' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Visitor/extendFinder.php',
        'Less_Visitor_joinSelector' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Visitor/joinSelector.php',
        'Less_Visitor_processExtends' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Visitor/processExtends.php',
        'Less_Visitor_toCSS' => __DIR__ . '/..' . '/wikimedia/less.php/lib/Less/Visitor/toCSS.php',
        'lessc' => __DIR__ . '/..' . '/wikimedia/less.php/lessc.inc.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit35e6f3ad324865b2b638cfc5ed3cc791::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit35e6f3ad324865b2b638cfc5ed3cc791::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit35e6f3ad324865b2b638cfc5ed3cc791::$prefixesPsr0;
            $loader->classMap = ComposerStaticInit35e6f3ad324865b2b638cfc5ed3cc791::$classMap;

        }, null, ClassLoader::class);
    }
}
