<?php

namespace BestIt\CtXmlMappingBundle;

use InvalidArgumentException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SimpleXMLElement;

/**
 * Maps the mapping config to the given model instance.
 * @author blange <lange@bestit-online.de>
 * @package BestIt\CtXmlMappingBundle
 * @version $id$
 */
class MappingManager
{
    use LoggerAwareTrait;

    /**
     * The mapping array.
     * @var array
     */
    private $mapping = [];

    /**
     * MappingManager constructor.
     * @param array $mapping
     * @param LoggerInterface|void $logger
     */
    public function __construct(array $mapping, LoggerInterface $logger = null)
    {
        $this
            ->setMapping($mapping)
            ->setLogger($logger ?? new NullLogger());
    }

    /**
     * Returns the used logger.
     * @return LoggerInterface
     */
    private function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Returns the mapping array.
     * @return array
     */
    private function getMapping(): array
    {
        return $this->mapping;
    }

    /**
     * Retutns the mapping for the given model.
     * @param $model
     * @return array
     */
    private function getMappingForModel($model): array
    {
        $found = array_filter($this->getMapping(), function (string $class) use ($model): bool {
            return $model instanceof $class;
        }, ARRAY_FILTER_USE_KEY);

        return $found ? reset($found) : [];
    }

    /**
     * Returns the old value for the given fieldpath.
     * @param string $fieldPath
     * @param mixed $model
     * @return mixed
     */
    private function getOldValue(string $fieldPath, $model)
    {
        $parts = explode('/', $fieldPath);
        $part = array_shift($parts);

        $oldValue = isset($model->$part) ? $model->$part : $model->{'get' . ucfirst($part)}();

        if ($parts) {
            $oldValue = $this->getOldValue(implode('/', $parts), $oldValue);
        }

        return $oldValue;
    }

    /**
     * Processes the nodes of the field config.
     * @param SimpleXMLElement $xmlElement
     * @param array $fieldConfig
     * @param bool $raw Process the found raw nodes.
     * @return array
     */
    private function processFieldMappingNodes(
        SimpleXMLElement $xmlElement,
        array $fieldConfig,
        bool $raw = false
    ): array {
        $values = array_map(function (string $nodePath) use ($raw, $xmlElement) {
            $returnValue = '';

            if ($foundNodes = $xmlElement->xpath($nodePath)) {
                if ($raw) {
                    $returnValue = $foundNodes;
                } else {
                    $foundNode = current($foundNodes);

                    // "Save" the children, if there are some.
                    $returnValue = count($foundNode) ? $foundNode->children() : (string)$foundNode;
                }
            }

            return $returnValue;
        }, $fieldConfig['nodes']);

        return array_filter($values, function ($value): bool {
            return !is_scalar($value) || trim($value) !== '';
        });
    }

    /**
     * Processes the field value by using the field config.
     * @param mixed $context The object which calls this method.
     * @param array $fieldConfig
     * @param string $fieldPath
     * @param mixed $model
     * @param SimpleXMLElement $xmlElement
     * @return mixed
     */
    private function processFieldValue(
        $context,
        array $fieldConfig,
        string $fieldPath,
        $model,
        SimpleXMLElement $xmlElement
    ) {
        $oldValue = $this->getOldValue($fieldPath, $model);

        if ($fieldConfig['default']) {
            $value = $fieldConfig['default'];
        } else {
            $value = '';
            $values = $this->processFieldMappingNodes($xmlElement, $fieldConfig, $raw = (bool) @ $fieldConfig['raw']);

            if ($values) {
                if (!$raw) {
                    // There can be no multiple values without an separator.
                    $value = (array_key_exists('separator', $fieldConfig))
                        ? implode($fieldConfig['separator'], $values)
                        : reset($values);
                } else {
                    $value = $values;
                }
            }
        }

        // The processor is still used, even with a default value, to get a dynamic value out of a possible config in
        // yaml or xml files.
        if ($fieldConfig['processor']) {
            foreach ($fieldConfig['processor'] as $callback) {
                $callValues = [$value];

                if (is_array($callback)) {
                    array_unshift($callback, $context);

                    $callValues[] = $oldValue;
                    $callValues[] = $model;
                }

                $value = call_user_func_array($callback, $callValues);
            }
        }

        return $value;
    }

    /**
     * Processes the values for the model fields.
     * @param mixed $context The object which calls this method.
     * @param array $mapping
     * @param mixed $model
     * @param SimpleXMLElement $xmlElement
     * @return mixed
     */
    private function processFieldValues($context, array $mapping, $model, SimpleXMLElement $xmlElement)
    {
        foreach ($mapping['fields'] as $fieldPath => $fieldConfig) {
            $value = $this->processFieldValue($context, $fieldConfig, $fieldPath, $model, $xmlElement);

            $this->setValue($fieldPath, $model, $value);
        }

        return $model;
    }

    /**
     * Matches the config to the given model or throws an exception.
     * @param mixed $context The object which calls this method.
     * @param mixed $model
     * @param SimpleXMLElement $xmlElement
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function processModel($context, $model, SimpleXMLElement $xmlElement)
    {
        $mapping = $this->getMappingForModel($model);

        if (!$mapping) {
            throw new InvalidArgumentException('Can not found the model mapping ' . get_class($model));
        }

        $this->processFieldValues($context, $mapping, $model, $xmlElement);

        return $model;
    }

    /**
     * Sets the mapping array.
     * @param array $mapping
     * @return MappingManager
     */
    private function setMapping(array $mapping): MappingManager
    {
        $this->mapping = $mapping;
        return $this;
    }

    /**
     * Sets the value on the given model matching the field path.
     * @param string $fieldPath
     * @param mixed $model
     * @param mixed $value
     */
    private function setValue(string $fieldPath, $model, $value)
    {
        $parts = explode('/', $fieldPath);
        $part = array_shift($parts);

        if ($parts) {
            $this->setValue(
                implode('/', $parts),
                isset($model->$part) ? $model->$part : $model->{'get' . ucfirst($part)}(),
                $value
            );
        } else {
            isset($model->$part) ? $model->$part = $value : $model->{'set' . ucfirst($part)}($value);
        }
    }
}
