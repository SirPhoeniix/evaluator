<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerSdk\Evaluator\ReleaseApp\Infrastructure\Client\Request;

use SprykerSdk\Evaluator\ReleaseApp\Domain\Client\Request\RequestInterface;

class HttpUpgradeAnalysisHttpRequest implements HttpRequestInterface
{
    /**
     * @var string
     */
    public const REQUEST_ENDPOINT = '/upgrade-analysis.json';

    /**
     * @var \SprykerSdk\Evaluator\ReleaseApp\Domain\Client\Request\RequestInterface
     */
    protected RequestInterface $domainRequest;

    /**
     * @param \SprykerSdk\Evaluator\ReleaseApp\Domain\Client\Request\RequestInterface $domainRequest
     */
    public function __construct(RequestInterface $domainRequest)
    {
        $this->domainRequest = $domainRequest;
    }

    /**
     * @return \SprykerSdk\Evaluator\ReleaseApp\Domain\Client\Request\RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->domainRequest;
    }

    /**
     * @return string
     */
    public function getEndpoint(): string
    {
        return static::REQUEST_ENDPOINT;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return static::REQUEST_METHOD_POST;
    }
}