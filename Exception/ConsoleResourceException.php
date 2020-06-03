<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Component\DataLoader\Exception;

use Klipper\Component\DoctrineExtra\Util\ClassUtils;
use Klipper\Component\Resource\ResourceInterface;
use Klipper\Component\Resource\ResourceListInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * The console exception for resource.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ConsoleResourceException extends RuntimeException
{
    /**
     * @param ResourceInterface|ResourceListInterface $resource
     */
    public function __construct(
        $resource,
        string $propertyPath = 'id',
        int $code = 0,
        \Throwable $previous = null
    ) {
        $message = '';

        if ($resource instanceof ResourceListInterface) {
            $message .= $this->buildResources($resource, $propertyPath);
        } elseif ($resource instanceof ResourceInterface) {
            $message .= $this->buildResource($resource, $propertyPath);
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Build the message for resources.
     *
     * @param ResourceListInterface $resources    The resources
     * @param string                $propertyPath The identifier property path of resource
     */
    protected function buildResources(ResourceListInterface $resources, string $propertyPath): string
    {
        $message = PHP_EOL.'Status: '.$resources->getStatus();

        if ($resources->getErrors()->count() > 0) {
            $message .= $this->buildErrors($resources->getErrors());
        }

        if ($resources->count() > 0) {
            $message .= PHP_EOL.'Resources:';
        }

        foreach ($resources->all() as $resource) {
            $message .= $this->buildResource($resource, $propertyPath, 2);
        }

        return $message;
    }

    /**
     * Build the message for resource.
     *
     * @param ResourceInterface $resource     The resource
     * @param string            $propertyPath The identifier property path of resource
     * @param int               $indent       The indentation
     */
    protected function buildResource(ResourceInterface $resource, string $propertyPath, int $indent = 0): string
    {
        $indentStr = sprintf("%{$indent}s", ' ');
        $accessor = PropertyAccess::createPropertyAccessor();
        $data = $resource->getRealData();

        $message = PHP_EOL.$indentStr.'- '.ClassUtils::getClass($data).': ';
        $message .= $accessor->getValue($data, $propertyPath);
        $message .= PHP_EOL.$indentStr.'  Status: '.$resource->getStatus();

        if ($resource->getErrors()->count() > 0) {
            $message .= $this->buildErrors($resource->getErrors(), 4);
        }

        return $message;
    }

    /**
     * Build the errors message.
     *
     * @param ConstraintViolationListInterface $violations The constraint violation
     * @param int                              $indent     The indentation
     */
    protected function buildErrors(ConstraintViolationListInterface $violations, int $indent = 0): string
    {
        $indentStr = sprintf("%{$indent}s", ' ');
        $message = PHP_EOL.$indentStr.'Errors:';

        /** @var ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $message .= PHP_EOL.$indentStr.'  - ';

            if (null !== $violation->getPropertyPath()) {
                $message .= 'Field "'.$violation->getPropertyPath().'": ';
            }

            $message .= $violation->getMessage();
        }

        return $message;
    }
}
