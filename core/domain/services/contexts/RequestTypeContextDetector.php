<?php

namespace EventEspresso\core\domain\services\contexts;

use EE_Request;
use EventEspresso\core\domain\Domain;
use InvalidArgumentException;
use EventEspresso\core\domain\entities\contexts\RequestTypeContext;

defined('EVENT_ESPRESSO_VERSION') || exit;



/**
 * Class RequestTypeContextDetector
 * Basically a Factory class for generating a RequestTypeContext DTO based on the current request
 *
 * @package EventEspresso\core\domain\services\contexts
 * @author  Brent Christensen
 * @since   4.9.51
 */
class RequestTypeContextDetector
{

    /**
     * @var RequestTypeContextFactory $factory
     */
    private $factory;

    /**
     * @var EE_Request $request
     */
    private $request;


    /**
     * RequestTypeContextDetector constructor.
     *
     * @param EE_Request                $request
     * @param RequestTypeContextFactory $factory
     */
    public function __construct(EE_Request $request, RequestTypeContextFactory $factory)
    {
        $this->request = $request;
        $this->factory = $factory;
    }


    /**
     * @return RequestTypeContext
     * @throws InvalidArgumentException
     */
    public function detectRequestTypeContext()
    {
        // Detect EE REST API
        if ($this->isEspressoRestApiRequest()) {
            return $this->factory->create(RequestTypeContext::API);
        }
        // Detect AJAX
        if ($this->request->isAjax()) {
            if ($this->request->isFrontAjax()) {
                return $this->factory->create(RequestTypeContext::FRONT_AJAX);
            }
            return $this->factory->create(RequestTypeContext::ADMIN_AJAX);
        }
        // Detect WP_Cron
        if ($this->isCronRequest()) {
            return $this->factory->create(RequestTypeContext::CRON);
        }
        // Detect command line requests
        if (defined('WP_CLI') && WP_CLI) {
            return $this->factory->create(RequestTypeContext::CLI);
        }
        // detect WordPress admin (ie: "Dashboard")
        if (is_admin()) {
            return $this->factory->create(RequestTypeContext::ADMIN);
        }
        // Detect iFrames
        if ($this->isIframeRoute()) {
            return $this->factory->create(RequestTypeContext::IFRAME);
        }
        // Detect Feeds
        if ($this->isFeedRequest()) {
            return $this->factory->create(RequestTypeContext::FEED);
        }
        // and by process of elimination...
        return $this->factory->create(RequestTypeContext::FRONTEND);
    }


    /**
     * @return bool
     */
    private function isEspressoRestApiRequest()
    {
        $ee_rest_url_prefix = trim(rest_get_url_prefix(), '/');
        $ee_rest_url_prefix .= '/' . Domain::API_NAMESPACE;
        return $this->uriPathMatches($ee_rest_url_prefix);
    }


    /**
     * @return bool
     */
    private function isCronRequest()
    {
        return $this->uriPathMatches('wp-cron.php');
    }


    /**
     * @return bool
     */
    private function isFeedRequest()
    {
        return $this->uriPathMatches('feed');
    }


    /**
     * @param string $component
     * @return bool
     */
    private function uriPathMatches($component)
    {
        $request_uri = $this->request->requestUri();
        $parts       = explode('?', $request_uri);
        $path        = trim(reset($parts), '/');
        return strpos($path, $component) === 0;
    }


    /**
     * @return bool
     */
    private function isIframeRoute()
    {
        $is_iframe_route = apply_filters(
            'FHEE__EventEspresso_core_domain_services_contexts_RequestTypeContextDetector__isIframeRoute',
            $this->request->get('event_list', '') === 'iframe'
            || $this->request->get('ticket_selector', '') === 'iframe'
            || $this->request->get('calendar', '') === 'iframe',
            $this
        );
        return filter_var($is_iframe_route, FILTER_VALIDATE_BOOLEAN);
    }

}
// Location: RequestTypeContextDetector.php