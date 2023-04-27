<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerSdk\Evaluator\Checker\PluginsRegistrationWithRestrictionsChecker;

use PhpParser\Comment\Doc;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use SprykerSdk\Evaluator\Checker\CheckerInterface;
use SprykerSdk\Evaluator\Dto\CheckerInputDataDto;
use SprykerSdk\Evaluator\Dto\ViolationDto;
use SprykerSdk\Evaluator\Finder\SourceFinderInterface;
use SprykerSdk\Evaluator\Parser\NodeFinderInterface;
use SprykerSdk\Evaluator\Parser\PhpParserInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class PluginsRegistrationWithRestrictionsChecker implements CheckerInterface
{
    /**
     * @var string
     */
    public const NAME = 'PLUGINS_REGISTRATION_WITH_RESTRICTIONS_CHECKER';

    /**
     * @var string
     */
    protected const DEPENDENCY_PROVIDER_PATTERN = '*DependencyProvider.php';

    /**
     * @var string
     */
    protected const CORE_MODULE_NAMESPACE_PREFIX = 'Spryker';

    /**
     * @var \SprykerSdk\Evaluator\Finder\SourceFinderInterface
     */
    protected SourceFinderInterface $sourceFinder;

    /**
     * @var \SprykerSdk\Evaluator\Parser\PhpParserInterface
     */
    protected PhpParserInterface $phpParser;

    /**
     * @var \SprykerSdk\Evaluator\Parser\NodeFinderInterface
     */
    protected NodeFinderInterface $nodeFinder;

    /**
     * @var \SprykerSdk\Evaluator\Checker\PluginsRegistrationWithRestrictionsChecker\RestrictionDocBlockValidator
     */
    protected RestrictionDocBlockValidator $restrictionDocBlockValidator;

    /**
     * @param \SprykerSdk\Evaluator\Finder\SourceFinderInterface $sourceFinder
     * @param \SprykerSdk\Evaluator\Parser\PhpParserInterface $phpParser
     * @param \SprykerSdk\Evaluator\Parser\NodeFinderInterface $nodeFinder
     * @param \SprykerSdk\Evaluator\Checker\PluginsRegistrationWithRestrictionsChecker\RestrictionDocBlockValidator $restrictionDocBlockValidator
     */
    public function __construct(
        SourceFinderInterface $sourceFinder,
        PhpParserInterface $phpParser,
        NodeFinderInterface $nodeFinder,
        RestrictionDocBlockValidator $restrictionDocBlockValidator
    ) {
        $this->sourceFinder = $sourceFinder;
        $this->phpParser = $phpParser;
        $this->nodeFinder = $nodeFinder;
        $this->restrictionDocBlockValidator = $restrictionDocBlockValidator;
    }

    /**
     * @param \SprykerSdk\Evaluator\Dto\CheckerInputDataDto $inputData
     *
     * @return array<\SprykerSdk\Evaluator\Dto\ViolationDto>
     */
    public function check(CheckerInputDataDto $inputData): array
    {
        $dependencyProvidersFiles = $this->findDependencyProviders($inputData->getPath());

        $violations = [];

        foreach ($dependencyProvidersFiles as $dependencyProvidersFile) {
            $violations[] = $this->checkDependencyProviderFile($dependencyProvidersFile, $inputData->getPath());
        }

        return array_merge(...$violations);
    }

    /**
     * @param \Symfony\Component\Finder\SplFileInfo $dependencyProviderFile
     * @param string $path
     *
     * @return array<\SprykerSdk\Evaluator\Dto\ViolationDto>
     */
    protected function checkDependencyProviderFile(SplFileInfo $dependencyProviderFile, string $path): array
    {
        $nodes = $this->phpParser->parse($dependencyProviderFile->getPathname());

//        if (!$this->isCoreClass($nodes)) {
//            return [];
//        }

        $violations = [];
        $filesClassUses = $this->getFileUsedClasses($nodes);
        $fileName = str_replace($path . '/', '', $dependencyProviderFile->getPathname());

        foreach ($this->getClassMethods($nodes) as $classMethod) {
            $arrays = $this->getArrays([$classMethod]);
            foreach ($arrays as $array) {
                foreach ($array->items as $item) {
                    if ($item === null) {
                        continue;
                    }

                    $violations[] = $this->checkArrayItem($item, $fileName, $filesClassUses);
                }
            }
        }

        return array_merge(...$violations);
    }

    /**
     * @param \PhpParser\Node\Expr\ArrayItem $arrayItem
     * @param string $fileName
     * @param array<string> $filesClassUses
     *
     * @return array<\SprykerSdk\Evaluator\Dto\ViolationDto>
     */
    protected function checkArrayItem(ArrayItem $arrayItem, string $fileName, array $filesClassUses): array
    {
        $docBlock = $arrayItem->getDocComment();

        if ($docBlock === null) {
            return [];
        }

        if (!$this->docBlockHasRestrictions($docBlock)) {
            return [];
        }

        return $this->validateDocBlock($docBlock, $fileName, $filesClassUses);
    }

    /**
     * @param \PhpParser\Comment\Doc $docBlock
     * @param string $fileName
     * @param array<string> $usedClassses
     *
     * @return array<\SprykerSdk\Evaluator\Dto\ViolationDto>
     */
    protected function validateDocBlock(Doc $docBlock, string $fileName, array $usedClassses): array
    {
        $messages = $this->restrictionDocBlockValidator->validate($docBlock->getText(), $usedClassses);

        return array_map(
            static fn (string $message): ViolationDto => new ViolationDto($message, sprintf('%s:%s', $fileName, $docBlock->getStartLine())),
            $messages,
        );
    }

    /**
     * @param array<\PhpParser\Node> $nodes
     *
     * @return bool
     */
    protected function isCoreClass(array $nodes): bool
    {
        /** @var array<\PhpParser\Node\Stmt\Namespace_> $nameSpaceNodes */
        $nameSpaceNodes = $this->nodeFinder->findInstanceOf($nodes, Namespace_::class);

        return count($nameSpaceNodes) === 1
            && $nameSpaceNodes[0]->name !== null
            && strpos($nameSpaceNodes[0]->name->getFirst(), static::CORE_MODULE_NAMESPACE_PREFIX) === 0;
    }

    /**
     * @param array<\PhpParser\Node> $nodes
     *
     * @return array<\PhpParser\Node\Stmt\ClassMethod>
     */
    protected function getClassMethods(array $nodes): array
    {
        /** @var array<\PhpParser\Node\Stmt\ClassMethod> $methodNodes */
        $methodNodes = $this->nodeFinder->findInstanceOf($nodes, ClassMethod::class);

        return $methodNodes;
    }

    /**
     * @param array<\PhpParser\Node> $nodes
     *
     * @return array<\PhpParser\Node\Expr\Array_>
     */
    protected function getArrays(array $nodes): array
    {
        /** @var array<\PhpParser\Node\Expr\Array_> $arrayNodes */
        $arrayNodes = $this->nodeFinder->findInstanceOf($nodes, Array_::class);

        return $arrayNodes;
    }

    /**
     * @param array<\PhpParser\Node> $nodes
     *
     * @return array<string>
     */
    protected function getFileUsedClasses(array $nodes): array
    {
        /** @var array<\PhpParser\Node\Stmt\Use_> $useNodes */
        $useNodes = $this->nodeFinder->findInstanceOf($nodes, Use_::class);

        $useClasses = [];

        foreach ($useNodes as $use) {
            foreach ($use->uses as $subUse) {
                $useClasses[] = '\\' . $subUse->name->toString();
            }
        }

        return $useClasses;
    }

    /**
     * @param \PhpParser\Comment\Doc $docBlock
     *
     * @return bool
     */
    protected function docBlockHasRestrictions(Doc $docBlock): bool
    {
        return (bool)preg_match('/\* +Restrictions:/', $docBlock->getText());
    }

    /**
     * @param string $path
     *
     * @return \Symfony\Component\Finder\Finder
     */
    protected function findDependencyProviders(string $path): Finder
    {
        return $this->sourceFinder->find([static::DEPENDENCY_PROVIDER_PATTERN], [$path]);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return static::NAME;
    }
}
