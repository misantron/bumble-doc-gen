<?php

declare(strict_types=1);

namespace BumbleDocGen\Render\EntityDocRender\PhpClassToMd;

use BumbleDocGen\Parser\Entity\ClassEntity;
use BumbleDocGen\Render\Context\Context;
use BumbleDocGen\Render\Context\DocumentedEntityWrapper;
use BumbleDocGen\Render\EntityDocRender\EntityDocRenderInterface;
use BumbleDocGen\Render\Twig\MainExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Rendering PHP classes into md format documents (for display on github)
 */
class PhpClassToMdDocRender implements EntityDocRenderInterface
{
    public const BLOCK_AFTER_MAIN_INFO = 'after_main_info';
    public const BLOCK_AFTER_HEADER = 'after_header';
    public const BLOCK_BEFORE_DETAILS = 'before_details';

    private Environment $twig;
    private ?Context $context = null;

    public function __construct()
    {
        $loader = new FilesystemLoader([
            __DIR__ . '/templates',
        ]);
        $this->twig = new Environment($loader);
    }

    public function getDocFileExtension(): string
    {
        return 'md';
    }

    public function isAvailableForEntity(DocumentedEntityWrapper $entityWrapper): bool
    {
        return is_a($entityWrapper->getDocumentTransformableEntity(), ClassEntity::class);
    }

    public function setContext(Context $context): void
    {
        static $mainExtension;
        if (!$mainExtension) {
            $mainExtension = new MainExtension($context);
            $this->twig->addExtension($mainExtension);
        } else {
            $mainExtension->changeContext($context);
        }
        $this->context = $context;
    }

    public function getRenderedText(DocumentedEntityWrapper $entityWrapper): string
    {
        return $this->twig->render('class.md.twig', [
            'classEntity' => $entityWrapper->getDocumentTransformableEntity(),
            'generationInitiatorFilePath' => $entityWrapper->getInitiatorFilePath(),
            'renderContext' => $this->context,
        ]);
    }
}
