<?php

declare(strict_types=1);

namespace BumbleDocGen\Render\Twig\Function;

use BumbleDocGen\Parser\Entity\ClassEntity;
use BumbleDocGen\Parser\Entity\ClassEntityCollection;
use BumbleDocGen\Render\Context\Context;
use BumbleDocGen\Render\Twig\Filter\HtmlToRst;

/**
 * Generate class map in HTML format
 *
 * @example {{ drawClassMap(classEntityCollection.filterByPaths(['/BumbleDocGen/Render'])) }}
 * @example {{ drawClassMap(classEntityCollection) }}
 */
final class DrawClassMap
{
    /** @var array<string, string> */
    private array $fileClassmap;

    public function __construct(private Context $context, private string $templateType = 'rst')
    {
    }

    /**
     * @param ClassEntityCollection ...$classEntityCollections
     *  The collection of entities for which the class map will be generated
     * @return string
     */
    public function __invoke(ClassEntityCollection ...$classEntityCollections): string
    {
        $structure = $this->convertDirectoryStructureToFormattedString(
            $this->getDirectoryStructure(...$classEntityCollections),
        );

        $content = "<pre>{$structure}</pre>";
        if ($this->templateType == 'rst') {
            $htmlToRstFunction = new HtmlToRst();
            return $htmlToRstFunction($content);
        }

        return $content;
    }

    protected function appendClassToDirectoryStructure(array $directoryStructure, ClassEntity $classEntity): array
    {
        $getDocumentedClassUrl = new GetDocumentedClassUrl($this->context);
        $this->fileClassmap[$classEntity->getFileName()] = $getDocumentedClassUrl($classEntity->getName());
        $fileName = ltrim($classEntity->getFileName(), DIRECTORY_SEPARATOR);
        $pathParts = array_reverse(explode(DIRECTORY_SEPARATOR, $fileName));
        $tmpStructure = [array_shift($pathParts)];
        $prevKey = '';
        foreach ($pathParts as $pathPart) {
            $tmpStructure[$pathPart] = $tmpStructure;
            unset($tmpStructure[$prevKey]);
            unset($tmpStructure[0]);
            $prevKey = $pathPart;
        }
        return array_merge_recursive($directoryStructure, $tmpStructure);
    }

    public function getDirectoryStructure(ClassEntityCollection ...$classEntityCollections): array
    {
        $directoryStructure = [];
        foreach ($classEntityCollections as $classEntityCollection) {
            foreach ($classEntityCollection as $classEntity) {
                $directoryStructure = $this->appendClassToDirectoryStructure($directoryStructure, $classEntity);
            }
        }
        return $directoryStructure;
    }

    private function sortStruct(array $structure): array
    {
        $sortedStructure = [];
        foreach ($structure as $key => $line) {
            if (is_array($line)) {
                $sortedStructure[$key] = $line;
            }
        }
        foreach ($structure as $key => $line) {
            if (!is_array($line)) {
                $sortedStructure[$key] = $line;
            }
        }
        return $sortedStructure;
    }

    public function convertDirectoryStructureToFormattedString(
        array $structure,
        string $prefix = '│',
        string $path = '/'
    ): string {
        $formattedString = '';
        $elementsCount = count($structure);
        $i = 0;
        $structure = $this->sortStruct($structure);
        foreach ($structure as $key => $line) {
            $isLastLine = ++$i == $elementsCount;
            $preparedPrefix = mb_substr($prefix, 0, -1) . ($isLastLine ? '└' : '├');
            if (is_array($line)) {
                $formattedString .= "{$preparedPrefix}──<b>{$key}</b>/\n";
                $formattedString .= $this->convertDirectoryStructureToFormattedString(
                    $line,
                    "{$prefix}  │",
                    "{$path}{$key}/"
                );
            } else {
                $filepath = "{$path}{$line}";
                $filepath = $this->fileClassmap[$filepath] ?? $filepath;
                $formattedString .= "{$preparedPrefix}── <a href='{$filepath}'>{$line}</a>\n";
            }
        }
        return $formattedString;
    }
}
