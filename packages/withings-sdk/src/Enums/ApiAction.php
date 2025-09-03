<?php

namespace Filipac\Withings\Enums;

enum ApiAction: string
{
    // Measure API Actions
    case GET_MEASURES = 'getmeas';
    case GET_ACTIVITY = 'getactivity';
    case GET_WORKOUTS = 'getworkouts';
    case GET_INTRADAY_ACTIVITY = 'getintradayactivity';

    // User API Actions
    case USER_GET_BY_USER_ID = 'getbyuserid';
    case USER_GET_DEVICE = 'getdevice';
    case USER_GET_GOALS = 'getgoals';
    case USER_GET_PREFERENCES = 'getprefs';

    // Notification API Actions
    case NOTIFICATION_SUBSCRIBE = 'subscribe';
    case NOTIFICATION_REVOKE = 'revoke';
    case NOTIFICATION_GET = 'get';
    case NOTIFICATION_LIST = 'list';

    // OAuth2 API Actions
    case OAUTH2_REQUEST_TOKEN = 'requesttoken';

    // Dropshipment API Actions
    case DROPSHIP_CREATE_ORDER = 'createorder';
    case DROPSHIP_UPDATE_ORDER = 'updateorder';
    case DROPSHIP_GET_ORDER = 'getorder';
    case DROPSHIP_LIST_ORDERS = 'listorders';
    case DROPSHIP_CANCEL_ORDER = 'cancelorder';
    case DROPSHIP_GET_PRODUCTS = 'getproducts';
    case DROPSHIP_GET_SHIPPING_METHODS = 'getshippingmethods';
}