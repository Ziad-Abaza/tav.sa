<?php

namespace Modules\ApiWebhookManager\Enums;

enum ErrorCode: string
{
    // Authentication & Authorization (401, 403)
    case AUTHENTICATION_REQUIRED = 'AUTHENTICATION_REQUIRED';
    case INVALID_TOKEN = 'INVALID_TOKEN';
    case TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    case TOKEN_REVOKED = 'TOKEN_REVOKED';
    case INSUFFICIENT_SCOPE = 'INSUFFICIENT_SCOPE';
    case UNAUTHORIZED = 'UNAUTHORIZED';
    case IP_NOT_WHITELISTED = 'IP_NOT_WHITELISTED';

    // Validation (400, 422)
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case INVALID_REQUEST = 'INVALID_REQUEST';
    case MISSING_REQUIRED_FIELD = 'MISSING_REQUIRED_FIELD';
    case INVALID_PARAMETER = 'INVALID_PARAMETER';
    case INVALID_MEDIA_TYPE = 'INVALID_MEDIA_TYPE';

    // Resources (404, 409)
    case NOT_FOUND = 'NOT_FOUND';
    case RESOURCE_CONFLICT = 'RESOURCE_CONFLICT';
    case DUPLICATE_RESOURCE = 'DUPLICATE_RESOURCE';
    case FEATURE_NOT_AVAILABLE = 'FEATURE_NOT_AVAILABLE';
    case RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';

    // Rate Limiting (429, 403)
    case RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    case FEATURE_LIMIT_EXCEEDED = 'FEATURE_LIMIT_EXCEEDED';

    // WhatsApp (422, 500)
    case WHATSAPP_NOT_CONFIGURED = 'WHATSAPP_NOT_CONFIGURED';
    case WHATSAPP_API_ERROR = 'WHATSAPP_API_ERROR';
    case MESSAGE_SEND_FAILED = 'MESSAGE_SEND_FAILED';
    case TEMPLATE_NOT_FOUND = 'TEMPLATE_NOT_FOUND';
    case INVALID_PHONE_NUMBER = 'INVALID_PHONE_NUMBER';
    case CONTACT_OPTED_OUT = 'CONTACT_OPTED_OUT';

    // Server (500, 503)
    case INTERNAL_ERROR = 'INTERNAL_ERROR';
    case SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    case DATABASE_ERROR = 'DATABASE_ERROR';

    public function httpStatus(): int
    {
        return match ($this) {
            self::AUTHENTICATION_REQUIRED,
            self::INVALID_TOKEN,
            self::TOKEN_EXPIRED,
            self::TOKEN_REVOKED => 401,

            self::INSUFFICIENT_SCOPE,
            self::UNAUTHORIZED,
            self::IP_NOT_WHITELISTED,
            self::FEATURE_LIMIT_EXCEEDED,
            self::CONTACT_OPTED_OUT => 403,

            self::NOT_FOUND,
            self::RESOURCE_NOT_FOUND,
            self::TEMPLATE_NOT_FOUND => 404,

            self::RESOURCE_CONFLICT,
            self::DUPLICATE_RESOURCE => 409,

            self::VALIDATION_ERROR,
            self::MISSING_REQUIRED_FIELD,
            self::WHATSAPP_NOT_CONFIGURED,
            self::INVALID_PHONE_NUMBER => 422,

            self::INVALID_REQUEST,
            self::INVALID_PARAMETER => 400,

            self::RATE_LIMIT_EXCEEDED => 429,

            self::SERVICE_UNAVAILABLE => 503,

            self::INTERNAL_ERROR,
            self::DATABASE_ERROR,
            self::WHATSAPP_API_ERROR,
            self::MESSAGE_SEND_FAILED => 500,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::AUTHENTICATION_REQUIRED => 'Authentication is required to access this resource.',
            self::INVALID_TOKEN => 'The provided API token is invalid.',
            self::TOKEN_EXPIRED => 'The API token has expired.',
            self::TOKEN_REVOKED => 'The API token has been revoked.',
            self::INSUFFICIENT_SCOPE => 'Your API token does not have the required permissions for this action.',
            self::UNAUTHORIZED => 'You are not authorized to perform this action.',
            self::IP_NOT_WHITELISTED => 'Your IP address is not whitelisted for this API token.',

            self::VALIDATION_ERROR => 'The given data was invalid.',
            self::INVALID_REQUEST => 'The request is invalid or malformed.',
            self::MISSING_REQUIRED_FIELD => 'A required field is missing from the request.',
            self::INVALID_PARAMETER => 'One or more parameters are invalid.',

            self::NOT_FOUND => 'The requested resource was not found.',
            self::RESOURCE_CONFLICT => 'The request conflicts with the current state of the resource.',
            self::DUPLICATE_RESOURCE => 'A resource with the same identifier already exists.',

            self::RATE_LIMIT_EXCEEDED => 'Too many requests. Please try again later.',
            self::FEATURE_LIMIT_EXCEEDED => 'Feature limit exceeded for your current plan.',

            self::WHATSAPP_NOT_CONFIGURED => 'WhatsApp connection is not configured for this account.',
            self::WHATSAPP_API_ERROR => 'An error occurred while communicating with WhatsApp API.',
            self::MESSAGE_SEND_FAILED => 'Failed to send the message.',
            self::TEMPLATE_NOT_FOUND => 'The specified template was not found.',
            self::INVALID_PHONE_NUMBER => 'The phone number is invalid or not registered with WhatsApp.',
            self::CONTACT_OPTED_OUT => 'The contact has opted out of receiving messages.',

            self::INTERNAL_ERROR => 'An internal server error occurred.',
            self::SERVICE_UNAVAILABLE => 'The service is temporarily unavailable.',
            self::DATABASE_ERROR => 'A database error occurred.',
        };
    }

    public function isClientError(): bool
    {
        return $this->httpStatus() >= 400 && $this->httpStatus() < 500;
    }

    public function isServerError(): bool
    {
        return $this->httpStatus() >= 500;
    }
}
