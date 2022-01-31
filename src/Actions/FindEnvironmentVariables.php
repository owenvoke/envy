<?php

declare(strict_types=1);

namespace Worksome\Envsync\Actions;

use Illuminate\Support\Collection;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NodeConnectingVisitor;
use PhpParser\Parser;
use Worksome\Envsync\Contracts\Actions\FindsEnvironmentVariables;
use Worksome\Envsync\Support\EnvironmentVariable;
use Worksome\Envsync\Support\PhpParser\EnvCallNodeVisitor;

use function Safe\file_get_contents;

final class FindEnvironmentVariables implements FindsEnvironmentVariables
{
    public function __construct(private Parser $parser)
    {
    }

    public function __invoke(string $filePath, bool $excludeVariablesWithDefaults = false): Collection
    {
        $traverser = new NodeTraverser();
        $envCallNodeVisitor = new EnvCallNodeVisitor($filePath);

        $traverser->addVisitor(new NodeConnectingVisitor());
        $traverser->addVisitor($envCallNodeVisitor);
        $statements = $this->parser->parse(file_get_contents($filePath));

        if ($statements === null) {
            return $envCallNodeVisitor->getEnvironmentVariables();
        }

        $traverser->traverse($statements);

        // @phpstan-ignore-next-line
        return $envCallNodeVisitor
            ->getEnvironmentVariables()
            ->when(
                $excludeVariablesWithDefaults,
                fn (Collection $variables) => $variables->reject(fn (EnvironmentVariable $variable) => $variable->hasDefault())
            );
    }
}