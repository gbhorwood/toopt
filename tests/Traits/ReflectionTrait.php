<?php
namespace Tests\Traits;


/**
 * Reflection methods for managing protected and private attributes
 *
 */
trait ReflectionTrait {

    /**
     * Get value of private or protected class property of objet $tooptObject
     *
     * @param  Toopt $toopt      The Toopt object
     * @param  String $property  The string of the property name
     */
    public function getInaccessibleProperty(\gbhorwood\toopt\Toopt $tooptObject, String $property):Array
    {
        $tooptClass = new \ReflectionClass($tooptObject);
        $reflecteProperty = $tooptClass->getProperty($property);
        $reflecteProperty->setAccessible(true);
        return $reflecteProperty->getValue($tooptObject);
    }

    /**
     * Make protected method in Toopt accessible
     *
     * @param  String $methodName
     * @return ReflectionMethod
     */
    public function setAccessible(String $methodName):\ReflectionMethod
    {
        $tooptClass = new \ReflectionClass('gbhorwood\toopt\Toopt');
        $method = $tooptClass->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}