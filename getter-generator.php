#!/usr/bin/env php
<?php

if (!isset($argv[1])) {
  echo "Usage : ./getter-generator.php <file to analyze>\n";
    exit;
}
if (!file_exists($argv[1])) {
    echo "File \"${argv[1]}\" not found\n";
    exit;
}
if (strpos(mime_content_type($argv[1]), 'php') === false) {
    echo "File \"${argv[1]}\" seems to not be a php file\n";
    exit;
}

$content = file_get_contents($argv[1]);
$contentLowered = strtolower($content);

include $argv[1];

$namespace = '';
preg_match('/namespace\s+([^\s]*)\s*;/', $content, $match);
if (!empty($match[1])) {
    $namespace = '\\' . $match[1] . '\\';
}

preg_match_all('/class\s+(\w*)/', $content, $matches);
if (empty($matches[1])) {
    echo "No classes found\n";
    exit;
}

$codeToAdd = array();

foreach ($matches[1] as $class) {
    $ref = new \ReflectionClass($namespace . $class); 
    $ecl = $ref->getEndLine(); // End class line
    
    $codeToAdd[$ecl] = '';
    
    
    $properties = $ref->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);
    foreach ($properties as $property) {
        
        $type = '';
        preg_match('/@var\s+(\w+)\s*\n/', $property->getDocComment(), $match);
        if (!empty($match[1])) {
            $type = $match[1];
        }
        
        $propertyCamelized = ucfirst($property->name);
        
        if (!$ref->hasMethod('set' . $property->name)) {
            $codeToAdd[$ecl] .= "
    /**
     * Set {$property->name}
     *
     * @param $type \${$property->name}
     * @return $class
     */
    public function set{$propertyCamelized}(\${$property->name})
    {
        \$this->{$property->name} = \${$property->name};
    
        return \$this;
    }
";
        }
        if (!$ref->hasMethod('get' . $property->name)) {
            $codeToAdd[$ecl] .= "
    /**
     * Get {$property->name}
     *
     * @return $type
     */
    public function get{$propertyCamelized}()
    {
        return \$this->{$property->name};
    }
";
        }
    }
}
krsort($codeToAdd);

$modified = false;
$file = file($argv[1], FILE_IGNORE_NEW_LINES);   
foreach ($codeToAdd as $line => $value) {
    if (!empty($value)) {
        array_splice($file, $line-1, 0, $value);
        $modified = true;
    }
}
if ($modified) {
    file_put_contents($argv[1], join("\n", $file));
}

echo "File updated \n";

function __autoload($className) {
    $parts = explode('\\', str_replace('\\\\', '\\', $className));
    $className = array_pop($parts);
    $namespace = implode('\\', $parts);

    $namespaceDefinition = '';
    if (!empty($namespace)) {
        $namespaceDefinition = "namespace $namespace;";
    }

    if (strpos($className, 'Interface') !== false) {
        eval("$namespaceDefinition interface $className {}");
    } else {
        eval("$namespaceDefinition class $className {}");
    }
}
