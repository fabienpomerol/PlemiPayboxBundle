<?php

/*
 * This file is part of the PlemiPayboxBundle.
 *
 * (c) Ludovic Fleury <ludovic.fleury@plemi.org>
 * (c) David Guyon <david.guyon@plemi.org>
 * (c) Erwann Mest <erwann.mest@plemi.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plemi\Bundle\PayboxBundle\Transport;

use Plemi\Bundle\PayboxBundle\PayboxSystem\PayboxRequest;

/**
 * Perform a call with cURL
 *
 * @author David Guyon <david.guyon@plemi.org>
 * @author Erwann Mest <erwann.mest@plemi.org>
 * @author Ludovic Fleury <ludovic.fleury@plemi.org>
 */
class CurlTransport extends AbstractTransport implements TransportInterface
{

    /**
     * enable or not the hmac authentication
     *
     * @var boolean $isHmacEnabled
     */
    protected $isHmacEnabled;

    /**
     * This is the secret key given by paybox
     *
     * @var string $secret The secrect
     */
    protected $secret;

    /**
     * Name of selected hashing algorithm (i.e. "md5", "sha256", "haval160,4", etc..)
     * See hash_algos() for a list of supported algorithms
     *
     * @var string $hash The hashing algorithm to use
     */
    protected $hash;

    /**
     * Constructor
     *
     * @param string $endpoint to paybox endpoint
     * @throws RuntimeException If cURL is not available
     */
    public function __construct($endpoint = '', $hmac)
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL is not available. Activate it first.');
        }

        if ($hmac['enabled'] && empty($hmac['secret'])) {
            throw new \RuntimeException('HMAC is enabled but you need to give a secret key.');
        }

        $this->isHmacEnabled = $hmac['enabled'];
        $this->hash          = $hmac['hash'];
        $this->secret        = $hmac['secret'];

        parent::__construct($endpoint);
    }

    /**
     * {@inheritDoc}
     *
     * @param PayboxRequest $request Request instance
     *
     * @throws RuntimeException On cURL error
     *
     * @return string $response The html of the temporary form
     */
    public function call(PayboxRequest $request)
    {
        $this->checkEndpoint();
        if ($this->isHmacEnabled) {
            $datas = $request->checkAndGetDatasWithHmac($this->secret, $this->hash);
        }
        else {
            $datas = $request->checkAndGetDatas();
        }

        $ch = curl_init();

        // cURL options
        $options = array(
                CURLOPT_URL => $this->getEndpoint(),
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($datas)
        );
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);

        $curlErrorNumber = curl_errno($ch);
        $curlErrorMessage = curl_error($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!in_array($responseCode, array(0, 200, 201, 204))) {
            throw new \RuntimeException('cUrl returns some errors (cURL errno '.$curlErrorNumber.'): '.$curlErrorMessage.' (HTTP Code: '.$responseCode.')');
        }

        curl_close($ch);

        return $response;
    }

}
