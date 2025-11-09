<?php

namespace App\GraphQL\Scalars;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;

class MixedScalar extends ScalarType
{
    public string $name = 'Mixed';
    
    public ?string $description = 'A flexible scalar type that can represent any JSON-encodable value (string, number, boolean, array, object, null).';

    /**
     * Serialize the value to send to the client
     */
    public function serialize($value): mixed
    {
        return $value;
    }

    /**
     * Parse the value from the client input
     */
    public function parseValue($value): mixed
    {
        return $value;
    }

    /**
     * Parse the value from the AST
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): mixed
    {
        // Convert the AST node to a PHP value
        return $this->parseValueNode($valueNode);
    }

    /**
     * Parse an AST node to PHP value
     */
    private function parseValueNode(Node $node): mixed
    {
        $className = get_class($node);
        
        if (str_contains($className, 'IntValueNode') || str_contains($className, 'FloatValueNode')) {
            return property_exists($node, 'value') ? (float) $node->value : 0;
        }
        
        if (str_contains($className, 'StringValueNode')) {
            return property_exists($node, 'value') ? $node->value : '';
        }
        
        if (str_contains($className, 'BooleanValueNode')) {
            return property_exists($node, 'value') ? $node->value : false;
        }
        
        if (str_contains($className, 'NullValueNode')) {
            return null;
        }
        
        if (str_contains($className, 'ListValueNode')) {
            if (property_exists($node, 'values')) {
                return array_map(function ($item) {
                    return $this->parseValueNode($item);
                }, iterator_to_array($node->values));
            }
            return [];
        }
        
        if (str_contains($className, 'ObjectValueNode')) {
            $object = [];
            if (property_exists($node, 'fields')) {
                foreach ($node->fields as $field) {
                    $object[$field->name->value] = $this->parseValueNode($field->value);
                }
            }
            return $object;
        }
        
        throw new Error('Cannot parse value node of type: ' . $className);
    }
}
