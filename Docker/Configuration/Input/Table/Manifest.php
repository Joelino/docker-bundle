<?php
namespace Keboola\DockerBundle\Docker\Configuration\Input\Table;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Manifest implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $root = $treeBuilder->root("table");
        self::configureNode($root);
        return $treeBuilder;
    }

    public static function configureNode(NodeDefinition $node)
    {
        $node
            ->children()
                ->scalarNode("id")->isRequired()->end()
                ->scalarNode("name")->end()
                ->scalarNode("uri")->end()
                ->arrayNode("primary_key")->prototype("scalar")->end()->end()
                ->arrayNode("indexed_columns")->prototype("scalar")->end()->end()
                ->scalarNode("created")->end()
                ->scalarNode("last_import_date")->end()
                ->scalarNode("last_change_date")->end()
                ->integerNode("rows_count")->end()
                ->integerNode("data_size_bytes")->end()
                ->booleanNode("is_alias")->end()
                ->arrayNode("columns")->prototype("scalar")->end()->end()
                ->arrayNode("attributes")->prototype("array")->children()
                    ->scalarNode("name")->end()
                    ->scalarNode("value")->end()
                    ->booleanNode("protected")->end()
                ->end()->end()->end()
            ;
    }
}