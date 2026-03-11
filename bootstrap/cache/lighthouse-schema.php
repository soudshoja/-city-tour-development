<?php return array (
  'types' => 
  array (
    'DotwMeta' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwMeta',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'trace_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Unique identifier for this request — use with X-Trace-ID header for log correlation.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'timestamp',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO 8601 timestamp when the response was generated (UTC).',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'company_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'The company_id of the authenticated company making this request.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'request_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Request identifier echoed back — same as trace_id, kept for backwards compatibility.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Metadata attached to every DOTW GraphQL response for tracing and debugging.',
        'block' => false,
      ),
    ),
    'DotwError' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwError',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwErrorCode',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Machine-readable error code for N8N workflow branching.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error_message',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'User-friendly message suitable for display in WhatsApp conversation.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error_details',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Technical error details for debugging — never shown to end users.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'action',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwErrorAction',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Suggested next action for the N8N workflow or caller.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Structured error returned by any DOTW operation when success is false.',
        'block' => false,
      ),
    ),
    'DotwErrorCode' => 
    array (
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwErrorCode',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'CREDENTIALS_NOT_CONFIGURED',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW API credentials are not configured for this company.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'CREDENTIALS_INVALID',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'The provided DOTW credentials are invalid (wrong username, password, or company code).',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'ALLOCATION_EXPIRED',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'The rate allocation has expired — the 3-minute window closed before confirmation.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'RATE_UNAVAILABLE',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'The selected rate is no longer available from the supplier.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'HOTEL_SOLD_OUT',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'The hotel is fully booked for the requested dates.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'PASSENGER_VALIDATION_FAILED',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'A required passenger field is missing or invalid.',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'API_TIMEOUT',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'The DOTW API did not respond within the 25-second timeout.',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'API_ERROR',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'The DOTW API returned an unexpected error.',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'CIRCUIT_BREAKER_OPEN',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'The circuit breaker is open — too many recent DOTW failures.',
            'block' => false,
          ),
        ),
        9 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'VALIDATION_ERROR',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'A validation error on the GraphQL input arguments.',
            'block' => false,
          ),
        ),
        10 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'INTERNAL_ERROR',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'An unexpected internal server error occurred.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Enumeration of all possible DOTW error codes.',
        'block' => false,
      ),
    ),
    'DotwErrorAction' => 
    array (
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwErrorAction',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'RETRY',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Call the same operation again — transient error.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'RETRY_IN_30_SECONDS',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Wait 30 seconds before retrying — rate limit or temporary overload.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'RECONFIGURE_CREDENTIALS',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Ask an admin to configure DOTW credentials for this company.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'RESEARCH',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Run a new searchHotels — the rate or allocation is no longer valid.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'CANCEL',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Do not retry — the booking has failed and manual intervention is needed.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'NONE',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'No specific action — informational error.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Suggested action for the caller (N8N workflow or client) after an error.',
        'block' => false,
      ),
    ),
    'DotwResponseEnvelope' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwResponseEnvelope',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the operation succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'True when the response was served from cache rather than a live DOTW API call.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Shared response envelope for all DOTW GraphQL operations. Every DOTW query and mutation returns a type that includes these fields, ensuring N8N workflows and Resayil can always parse responses predictably regardless of operation.',
        'block' => false,
      ),
    ),
    'GetCitiesResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetCitiesResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the request succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'GetCitiesData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of DOTW-serveable cities for the requested country.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response from the getCities query.',
        'block' => false,
      ),
    ),
    'GetCitiesData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetCitiesData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cities',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'DotwCity',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Cities available for hotel search in this country.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total number of cities returned.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Container for city list results.',
        'block' => false,
      ),
    ),
    'DotwCity' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwCity',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW city code — use this as the destination in searchHotels.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Human-readable city name.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A city served by DOTW in the requested country.',
        'block' => false,
      ),
    ),
    'SearchHotelsInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'SearchHotelsInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'destination',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'City code from getCities (e.g. DXB for Dubai). Accepts DOTW city code only — use getCities to resolve city names to codes.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkin',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-in date in YYYY-MM-DD format.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkout',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-out date in YYYY-MM-DD format.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rooms',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'SearchHotelRoomInput',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Room configuration — one entry per room requested. Supports multi-room complex itineraries (B2B-01).',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currency',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Response currency code (e.g. KWD, USD). Passed through to DOTW as-is. Defaults to USD if not provided.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'filters',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'SearchHotelsFiltersInput',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Optional filters for rating, price range, property type, meal plan, amenities, and cancellation policy (B2B-02 — full DOTW V4 vocabulary).',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Input for the searchHotels query.',
        'block' => false,
      ),
    ),
    'SearchHotelRoomInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'SearchHotelRoomInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'adultsCode',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of adults in this room.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'children',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Int',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Ages of children in this room. Provide an empty array if no children.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'passengerNationality',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO 3166-1 alpha-2 nationality code for occupants (e.g. AE, KW). Optional.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'passengerCountryOfResidence',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO 3166-1 alpha-2 country of residence code for occupants. Optional.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Occupancy configuration for a single room.',
        'block' => false,
      ),
    ),
    'SearchHotelsFiltersInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'SearchHotelsFiltersInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'minRating',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Minimum star rating to include (1–5). Omit for no minimum.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'maxRating',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Maximum star rating to include (1–5). Omit for no maximum.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'minPrice',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Float',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Minimum total price filter.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'maxPrice',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Float',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Maximum total price filter.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'propertyType',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Property type filter (e.g. hotel, apartment, resort). Maps to DOTW fieldName=propertytype.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'mealPlanType',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Meal plan type filter (e.g. BB, HB, FB, AI, RO, SC). Maps to DOTW fieldName=mealplantype.',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'amenities',
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Amenity codes to require (e.g. pool, wifi). Maps to DOTW fieldName=amenities.',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cancellationPolicy',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Cancellation policy type (e.g. refundable, non-refundable). Maps to DOTW fieldName=cancellation.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Optional filter criteria for hotel search. All fields are optional — omit to search without filters.',
        'block' => false,
      ),
    ),
    'SearchHotelsResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'SearchHotelsResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the search succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'True if results were served from the 2.5-minute per-company search cache.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'SearchHotelsData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Search result data — present only when success is true.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response from the searchHotels query.',
        'block' => false,
      ),
    ),
    'SearchHotelsData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'SearchHotelsData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotels',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'HotelSearchResult',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotels matching the search criteria with cheapest rate per meal plan per room type.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total number of hotels returned.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Container for hotel search results.',
        'block' => false,
      ),
    ),
    'HotelSearchResult' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'HotelSearchResult',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW hotel ID. Pass this as hotel_code to getRoomRates (Phase 5) for full hotel details and rate blocking.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rooms',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'HotelRoomResult',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Room configurations with cheapest rate per meal plan.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A single hotel result from the DOTW searchhotels API.
NOTE: Hotel name, city name, star rating, and image_url are NOT available from the DOTW searchhotels command.
These metadata fields are returned by the getRoomRates query (Phase 5). Use hotel_code from this result
as the hotel_code input to getRoomRates to obtain full hotel details.',
        'block' => true,
      ),
    ),
    'HotelRoomResult' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'HotelRoomResult',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'adults',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of adults this room accommodates.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'children',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of children.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'children_ages',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Child ages as a comma-separated string (from DOTW XML childrenages attribute).',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'room_types',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'RoomTypeRate',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Available room types with cheapest rate per meal plan.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Room occupancy result for a hotel — cheapest rate per meal plan from DOTW searchhotels.',
        'block' => false,
      ),
    ),
    'RoomTypeRate' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'RoomTypeRate',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW room type code.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Room type name.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rate_basis_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Rate basis ID — maps to meal plan: 1331=RO, 1332=BB, 1333=HB, 1334=FB, 1335=AI, 1336=SC.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currency_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Currency code from DOTW rateType currencyid attribute (passed through as-is — no conversion).',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'non_refundable',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'True if this rate is non-refundable.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total fare before markup.',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'markup',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'RateMarkup',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Markup applied to the fare: original_fare, markup_percent, markup_amount, final_fare.',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_taxes',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total taxes.',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_minimum_selling',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Minimum selling price (MSP) — never undercut this amount.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A room type with its cheapest rate for a specific meal plan — all DOTW searchhotels fields included (B2B-03).',
        'block' => false,
      ),
    ),
    'RateMarkup' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'RateMarkup',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'original_fare',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Original fare from DOTW before markup.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'markup_percent',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Markup percentage applied (from company credential markup_percent).',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'markup_amount',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Markup amount added.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'final_fare',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Final fare after markup — this is the selling price.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Markup breakdown for a rate — transparent pricing for WhatsApp display (MARKUP-03).',
        'block' => false,
      ),
    ),
    'GetRoomRatesInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetRoomRatesInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_code',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW hotel identifier. Obtained from searchHotels response hotel_code field.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkin',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-in date in YYYY-MM-DD format.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkout',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-out date in YYYY-MM-DD format.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rooms',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'SearchHotelRoomInput',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Room configuration. Reuses SearchHotelRoomInput from Phase 4.
Must match the room config used in the searchHotels call that produced this hotel_code.',
            'block' => true,
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currency',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Optional currency code (e.g. KWD, USD). When omitted, DOTW account default applies.',
            'block' => false,
          ),
        ),
      ),
    ),
    'BlockRatesInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'BlockRatesInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_code',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW hotel identifier. Must match the hotel_code used in the getRoomRates call.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_name',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Optional hotel name from caller context. DOTW getRooms does not return hotel metadata.
Caller should pass hotel name obtained from searchHotels or their hotel directory.
Stored in dotw_prebooks.hotel_name for booking reference. Falls back to empty string.',
            'block' => true,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkin',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-in date in YYYY-MM-DD format.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkout',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-out date in YYYY-MM-DD format.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rooms',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'SearchHotelRoomInput',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Room configuration. Must match the config used in getRoomRates.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'selected_room_type',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Room type code from getRoomRates response room_type_code field.',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'selected_rate_basis',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Rate basis ID from getRoomRates response rate_basis_id field (e.g. 1332 = Bed & Breakfast).',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'allocation_details',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Opaque allocation token from getRoomRates response rate_details[].allocation_details field.
Pass this value verbatim — any modification will cause DOTW to reject the blocking call.',
            'block' => true,
          ),
        ),
        8 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currency',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Optional currency code. When omitted, DOTW account default applies.',
            'block' => false,
          ),
        ),
      ),
    ),
    'GetRoomRatesResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetRoomRatesResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the operation succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Populated on failure. Null on success.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always present. Contains trace_id, timestamp, company_id.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — getRoomRates never caches results.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'GetRoomRatesData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Populated on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
    ),
    'GetRoomRatesData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetRoomRatesData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW hotel identifier (echo of input hotel_code).',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rooms',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'RoomRateResult',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'All room types returned by DOTW for this hotel, dates, and room config.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total number of room types returned.',
            'block' => false,
          ),
        ),
      ),
    ),
    'RoomRateResult' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'RoomRateResult',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'room_type_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW room type code. Pass as selected_room_type to blockRates.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'room_name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Human-readable room type name.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rate_details',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'RateDetail',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'All rate details for this room type, one per meal plan / rate basis.',
            'block' => false,
          ),
        ),
      ),
    ),
    'RateDetail' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'RateDetail',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rate_basis_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Rate basis code. Mapping:
1331 = Room Only, 1332 = Bed & Breakfast, 1333 = Half Board,
1334 = Full Board, 1335 = All Inclusive, 1336 = Self Catering.
Pass as selected_rate_basis to blockRates.',
            'block' => true,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rate_basis_name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Human-readable meal plan name (e.g. Bed & Breakfast, Half Board).',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'is_refundable',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the rate is refundable. Non-refundable rates cannot be cancelled.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_fare',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Base fare from DOTW before markup (pre-markup price).',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_taxes',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Tax amount from DOTW.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_price',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total price inclusive of taxes, before markup.',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'markup',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'RateMarkup',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Transparent markup breakdown. Use markup.final_fare as the customer-facing price.',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'allocation_details',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Opaque allocation token from DOTW. Pass verbatim as allocation_details to blockRates.
Do not modify, encode, or truncate — token corruption causes DOTW to reject the block call.',
            'block' => true,
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cancellation_rules',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'CancellationRule',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Cancellation policy rules. Empty array if no policy data returned by DOTW.',
            'block' => false,
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'original_currency',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Currency code of the rate as returned by DOTW (e.g. KWD, USD). Empty string if DOTW does not include currency in this rate. (RATE-05)',
            'block' => false,
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'exchange_rate',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Float',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Exchange rate applied if DOTW performed currency conversion. Null when no conversion occurred. (RATE-05)',
            'block' => false,
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'final_currency',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Currency code of the final customer-facing price after markup. Matches original_currency when no conversion. (RATE-05)',
            'block' => false,
          ),
        ),
      ),
    ),
    'CancellationRule' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'CancellationRule',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'from_date',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Start of the cancellation penalty window (ISO 8601).',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'to_date',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'End of the cancellation penalty window (ISO 8601).',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'charge',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Cancellation charge amount in the booking currency.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cancel_charge',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Cancel charge (may differ from charge in some DOTW responses).',
            'block' => false,
          ),
        ),
      ),
    ),
    'BlockRatesResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'BlockRatesResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the blocking operation succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Populated on failure. Null on success.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always present. Contains trace_id, timestamp, company_id.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — blockRates never caches results.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'BlockRatesData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Populated on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
    ),
    'BlockRatesData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'BlockRatesData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'prebook_key',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'UUID prebook key. Pass to createPreBooking (Phase 6) to confirm the booking.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'expires_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO 8601 timestamp when the rate lock expires (3 minutes from block call).',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'countdown_timer_seconds',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Seconds remaining until expiry at response time. Computed from expires_at.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW hotel identifier (echo of input hotel_code).',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotel name from input or empty string if caller did not provide it.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'room_type',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Room type code that was locked.',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rate_basis',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Rate basis ID of the locked rate.',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_fare',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total fare after markup applied (customer-facing price).',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_tax',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Tax amount.',
            'block' => false,
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'markup',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'RateMarkup',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Transparent markup breakdown for the locked rate.',
            'block' => false,
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'is_refundable',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the locked rate is refundable.',
            'block' => false,
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cancellation_rules',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'CancellationRule',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Cancellation rules applicable to the locked rate.',
            'block' => false,
          ),
        ),
      ),
    ),
    'PassengerInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PassengerInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'salutation',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Salutation code: 1=Mr, 2=Mrs, 3=Ms, 4=Dr, 5=Prof.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'firstName',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Passenger first name.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'lastName',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Passenger last name / family name.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'nationality',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO 3166-1 alpha-2 nationality code (e.g. KW, AE, GB).',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'residenceCountry',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO 3166-1 alpha-2 country of residence code.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'email',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Guest email address for booking confirmation communication.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Passenger details for DOTW hotel booking confirmation.
Salutation codes: 1=Mr, 2=Mrs, 3=Ms, 4=Dr, 5=Prof.',
        'block' => true,
      ),
    ),
    'CreatePreBookingInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'CreatePreBookingInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'prebook_key',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'UUID prebook key from blockRates response. Identifies the locked rate.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkin',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-in date in YYYY-MM-DD format. Must match the dates used in blockRates.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkout',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-out date in YYYY-MM-DD format. Must match the dates used in blockRates.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'passengers',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'PassengerInput',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Passenger details. One entry per adult per room.
Count must match total adults in the room configuration used in blockRates.
First passenger is treated as lead guest for email communication.',
            'block' => true,
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rooms',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'SearchHotelRoomInput',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Room configuration. Reuses SearchHotelRoomInput from Phase 4.
Required to reconstruct room occupancy (adultsCode, children) for DOTW confirmBooking call.
Must match the room configuration used in the original blockRates call.',
            'block' => true,
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'destination',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Optional city/destination code for alternative hotel suggestions.
Used when rate is no longer available (ERROR-04) — enables searching 3 nearby alternatives.
Same city code as the original searchHotels call. Omit if alternatives are not needed.',
            'block' => true,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Input for createPreBooking mutation.
Converts a locked prebook (from blockRates) into a confirmed DOTW booking.',
        'block' => true,
      ),
    ),
    'BookingItinerary' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'BookingItinerary',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW hotel identifier.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotel name from prebook context.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkin',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-in date (YYYY-MM-DD).',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkout',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-out date (YYYY-MM-DD).',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'room_type',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Room type code.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rate_basis',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Rate basis name (e.g. Bed and Breakfast, Half Board).',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_fare',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total fare with markup applied (customer-facing price).',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currency',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Currency code (e.g. KWD, USD).',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'is_refundable',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the booking is refundable.',
            'block' => false,
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'lead_guest_name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Lead passenger full name (salutation + firstName + lastName).',
            'block' => false,
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'customer_reference',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'UUID customer reference generated for DOTW. Use for booking amendments or disputes.',
            'block' => false,
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'confirmation_number',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Secondary DOTW confirmation number if provided. Empty string when not returned.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Hotel booking itinerary details returned on successful DOTW confirmation.',
        'block' => false,
      ),
    ),
    'CreatePreBookingData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'CreatePreBookingData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'booking_confirmation_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW booking confirmation code (bookingCode from confirmBooking response).',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'booking_status',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Booking status (e.g. confirmed).',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'itinerary_details',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'BookingItinerary',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Booking itinerary details for WhatsApp display.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'alternatives',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'HotelSearchResult',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Alternative hotels suggested when rate was unavailable (ERROR-04/ERROR-06).
Empty array on successful booking. Up to 3 results from searchHotels with same destination.',
            'block' => true,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Data payload on successful createPreBooking response.',
        'block' => false,
      ),
    ),
    'CreatePreBookingResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'CreatePreBookingResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'True when booking was confirmed successfully.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error when booking failed.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Request metadata: trace_id, company_id, timestamp.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — booking is a side-effecting mutation, never cached.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'CreatePreBookingData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Booking data on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response envelope for createPreBooking mutation.',
        'block' => false,
      ),
    ),
    'SaveBookingInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'SaveBookingInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'prebook_key',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'UUID prebook key from blockRates response.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkin',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-in date in YYYY-MM-DD format.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkout',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-out date in YYYY-MM-DD format.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'passengers',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'PassengerInput',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Passenger details, one per adult per room.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rooms',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'SearchHotelRoomInput',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Room configuration matching blockRates call.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'destination',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Optional city/destination code for alternative suggestions.',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'specialRequests',
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Optional special requests to pass to the hotel.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Input for the saveBooking mutation — same passenger/room structure as createPreBooking plus optional special requests.',
        'block' => false,
      ),
    ),
    'BookItineraryInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'BookItineraryInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'itinerary_code',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Itinerary code from saveBooking response.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Input for the bookItinerary mutation — takes the itinerary code returned by saveBooking.',
        'block' => false,
      ),
    ),
    'GetBookingDetailsInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetBookingDetailsInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'booking_code',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW booking confirmation code.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Input for the getBookingDetails query.',
        'block' => false,
      ),
    ),
    'SearchBookingsInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'SearchBookingsInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'from_date',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Start of the date range to search (YYYY-MM-DD). Optional.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'to_date',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'End of the date range to search (YYYY-MM-DD). Optional.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'customer_reference',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Customer reference to search for. Optional.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Input for the searchBookings query — at least one field must be provided.',
        'block' => false,
      ),
    ),
    'SaveBookingData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'SaveBookingData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'itinerary_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Itinerary code (from DOTW savebooking response) — pass to bookItinerary.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW hotel identifier echoed from the prebook.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'is_apr',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'True when rate was non-refundable (APR booking).',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Data returned by saveBooking — the itinerary code to pass to bookItinerary.',
        'block' => false,
      ),
    ),
    'BookItineraryData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'BookItineraryData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'booking_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW booking confirmation code (final reference after bookitinerary call).',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'booking_status',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Booking status returned by DOTW.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Data returned by bookItinerary — the final confirmed booking code.',
        'block' => false,
      ),
    ),
    'BookingDetails' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'BookingDetails',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'booking_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW booking confirmation code.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW hotel identifier.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'from_date',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-in date (YYYY-MM-DD).',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'to_date',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-out date (YYYY-MM-DD).',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Current booking status (e.g. confirmed, cancelled).',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'customer_reference',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Customer reference used when booking was created.',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_amount',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total amount charged.',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currency',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Currency code of the booking.',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'passengers',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Passenger details as a JSON-encoded string (DOTW returns varying structures).',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Full booking details returned by getBookingDetails.',
        'block' => false,
      ),
    ),
    'BookingSummary' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'BookingSummary',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'booking_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW booking confirmation code.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'customer_reference',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Customer reference used at booking time.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Current booking status.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW hotel identifier.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'from_date',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-in date.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'to_date',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-out date.',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_amount',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total amount charged.',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currency',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Currency code.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A single booking summary from searchBookings results.',
        'block' => false,
      ),
    ),
    'SearchBookingsData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'SearchBookingsData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'bookings',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'BookingSummary',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of bookings matching the search criteria.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total number of bookings returned.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Container for searchBookings results.',
        'block' => false,
      ),
    ),
    'SaveBookingResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'SaveBookingResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the save operation succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — saveBooking is a side-effecting mutation, never cached.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'SaveBookingData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Saved booking data on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response envelope for saveBooking mutation.',
        'block' => false,
      ),
    ),
    'BookItineraryResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'BookItineraryResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the itinerary booking succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — bookItinerary is a side-effecting mutation, never cached.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'BookItineraryData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Booking confirmation data on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response envelope for bookItinerary mutation.',
        'block' => false,
      ),
    ),
    'GetBookingDetailsResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetBookingDetailsResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the query succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — getBookingDetails never caches results.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'BookingDetails',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Full booking details on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response envelope for getBookingDetails query.',
        'block' => false,
      ),
    ),
    'SearchBookingsResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'SearchBookingsResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the search succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — searchBookings never caches results.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'SearchBookingsData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Search results on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response envelope for searchBookings query.',
        'block' => false,
      ),
    ),
    'CheckCancellationInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'CheckCancellationInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'booking_code',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW booking confirmation code to check cancellation charge for.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Input for the checkCancellation query — retrieve cancellation charge before committing.',
        'block' => false,
      ),
    ),
    'CancelBookingInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'CancelBookingInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'booking_code',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW booking confirmation code to cancel.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'penalty_applied',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Penalty amount returned by checkCancellation. Must be passed back verbatim to confirm cancellation.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Input for the cancelBooking mutation — confirm cancellation with the penalty amount from checkCancellation.',
        'block' => false,
      ),
    ),
    'DeleteItineraryInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DeleteItineraryInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'itinerary_code',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Itinerary code returned from saveBooking (APR flow only).',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Input for the deleteItinerary mutation — remove a saved (unconfirmed) APR itinerary.',
        'block' => false,
      ),
    ),
    'CancellationChargeData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'CancellationChargeData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'booking_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW booking confirmation code (echo of input).',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'charge',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Cancellation charge amount. Pass this as penalty_applied to cancelBooking.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currency',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Currency of the cancellation charge. Empty string if DOTW does not return currency in cancel query.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'is_outside_deadline',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'True when charge is 0.0 — indicates the booking is outside the penalty deadline (free cancellation).',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Data returned by checkCancellation — the charge incurred if cancellation is confirmed.',
        'block' => false,
      ),
    ),
    'CancelBookingData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'CancelBookingData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'booking_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW booking confirmation code (echo of input).',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cancelled',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'True when the cancellation was successfully processed by DOTW.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'penalty_applied',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Penalty amount that was applied (echo of input penalty_applied).',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'products_left_on_itinerary',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of products remaining on the itinerary after cancellation.
0 = full cancellation, >0 = partial cancellation (some rooms/products still active).
Maps to DOTW productsLeftOnItinerary field.',
            'block' => true,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Data returned by cancelBooking — result of confirmed cancellation.',
        'block' => false,
      ),
    ),
    'DeleteItineraryData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DeleteItineraryData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'itinerary_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Itinerary code that was deleted (echo of input).',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'deleted',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'True when the itinerary was successfully deleted.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Data returned by deleteItinerary — confirmation of itinerary deletion.',
        'block' => false,
      ),
    ),
    'CheckCancellationResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'CheckCancellationResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the operation succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — checkCancellation is never cached.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'CancellationChargeData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Cancellation charge data on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response envelope for the checkCancellation query.',
        'block' => false,
      ),
    ),
    'CancelBookingResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'CancelBookingResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the operation succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — cancelBooking is a side-effecting mutation, never cached.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'CancelBookingData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Cancellation result data on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response envelope for the cancelBooking mutation.',
        'block' => false,
      ),
    ),
    'DeleteItineraryResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DeleteItineraryResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the operation succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — deleteItinerary is a side-effecting mutation, never cached.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DeleteItineraryData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Deletion result data on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response envelope for the deleteItinerary mutation.',
        'block' => false,
      ),
    ),
    'DotwCodeItem' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwCodeItem',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW reference code — use as a filter value in search or booking requests.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Human-readable name for display purposes.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A simple code/name pair returned by DOTW reference lookup queries.',
        'block' => false,
      ),
    ),
    'DotwAmenityItem' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwAmenityItem',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'DOTW amenity code — use in hotel search amenity filters.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Human-readable amenity name.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'category',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Category of the amenity: \'amenity\', \'leisure\', or \'business\'.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'An amenity/leisure/business facility item returned by the getAmenityIds query.',
        'block' => false,
      ),
    ),
    'GetAllCountriesData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetAllCountriesData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'countries',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'DotwCodeItem',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'All DOTW internal country codes.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total number of countries returned.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Container for getAllCountries query results.',
        'block' => false,
      ),
    ),
    'GetServingCountriesData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetServingCountriesData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'countries',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'DotwCodeItem',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Countries for which DOTW has hotel inventory.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total number of serving countries returned.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Container for getServingCountries query results.',
        'block' => false,
      ),
    ),
    'GetHotelClassificationsData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetHotelClassificationsData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'classifications',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'DotwCodeItem',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotel star rating classification codes.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total number of classifications returned.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Container for getHotelClassifications query results.',
        'block' => false,
      ),
    ),
    'GetLocationIdsData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetLocationIdsData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'locations',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'DotwCodeItem',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Location filtering codes for hotel search.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total number of locations returned.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Container for getLocationIds query results.',
        'block' => false,
      ),
    ),
    'GetAmenityIdsData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetAmenityIdsData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'amenities',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'DotwAmenityItem',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Amenity, leisure, and business facility codes merged into one list.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total number of amenity items returned across all categories.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Container for getAmenityIds query results (merged from 3 DOTW commands).',
        'block' => false,
      ),
    ),
    'GetPreferenceIdsData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetPreferenceIdsData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'preferences',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'DotwCodeItem',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotel preference codes.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total number of preference codes returned.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Container for getPreferenceIds query results.',
        'block' => false,
      ),
    ),
    'GetChainIdsData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetChainIdsData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'chains',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'DotwCodeItem',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotel chain affiliation codes.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total number of chain codes returned.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Container for getChainIds query results.',
        'block' => false,
      ),
    ),
    'GetAllCountriesResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetAllCountriesResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the operation succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — lookup queries are not cached.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'GetAllCountriesData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Country list data on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response from the getAllCountries query.',
        'block' => false,
      ),
    ),
    'GetServingCountriesResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetServingCountriesResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the operation succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — lookup queries are not cached.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'GetServingCountriesData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Serving country list data on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response from the getServingCountries query.',
        'block' => false,
      ),
    ),
    'GetHotelClassificationsResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetHotelClassificationsResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the operation succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — lookup queries are not cached.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'GetHotelClassificationsData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotel classification data on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response from the getHotelClassifications query.',
        'block' => false,
      ),
    ),
    'GetLocationIdsResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetLocationIdsResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the operation succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — lookup queries are not cached.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'GetLocationIdsData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Location codes data on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response from the getLocationIds query.',
        'block' => false,
      ),
    ),
    'GetAmenityIdsResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetAmenityIdsResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the operation succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — lookup queries are not cached.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'GetAmenityIdsData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Amenity data on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response from the getAmenityIds query.',
        'block' => false,
      ),
    ),
    'GetPreferenceIdsResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetPreferenceIdsResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the operation succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — lookup queries are not cached.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'GetPreferenceIdsData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Preference codes data on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response from the getPreferenceIds query.',
        'block' => false,
      ),
    ),
    'GetChainIdsResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetChainIdsResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the operation succeeded.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DotwError',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Structured error — present only when success is false.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwMeta',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Per-request tracing metadata — always present.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cached',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Always false — lookup queries are not cached.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'GetChainIdsData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Chain codes data on success. Null on failure.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response from the getChainIds query.',
        'block' => false,
      ),
    ),
    'DateTime' => 
    array (
      'kind' => 'ScalarTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DateTime',
      ),
      'directives' => 
      array (
        0 => 
        array (
          'kind' => 'Directive',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'scalar',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'Argument',
              'value' => 
              array (
                'kind' => 'StringValue',
                'value' => 'Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime',
                'block' => false,
              ),
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'class',
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A datetime string with format `Y-m-d H:i:s`, e.g. `2018-05-23 13:43:32`.',
        'block' => false,
      ),
    ),
    'Date' => 
    array (
      'kind' => 'ScalarTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Date',
      ),
      'directives' => 
      array (
        0 => 
        array (
          'kind' => 'Directive',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'scalar',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'Argument',
              'value' => 
              array (
                'kind' => 'StringValue',
                'value' => 'Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\Date',
                'block' => false,
              ),
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'class',
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A date string with format `Y-m-d`, e.g. `2025-11-01`.',
        'block' => false,
      ),
    ),
    'Mixed' => 
    array (
      'kind' => 'ScalarTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Mixed',
      ),
      'directives' => 
      array (
        0 => 
        array (
          'kind' => 'Directive',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'scalar',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'Argument',
              'value' => 
              array (
                'kind' => 'StringValue',
                'value' => 'App\\GraphQL\\Scalars\\MixedScalar',
                'block' => false,
              ),
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'class',
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Mixed scalar type for flexible JSON data.',
        'block' => false,
      ),
    ),
    'ISODateTime' => 
    array (
      'kind' => 'ScalarTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'ISODateTime',
      ),
      'directives' => 
      array (
        0 => 
        array (
          'kind' => 'Directive',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'scalar',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'Argument',
              'value' => 
              array (
                'kind' => 'StringValue',
                'value' => 'App\\GraphQL\\Scalars\\ISODateTimeScalar',
                'block' => false,
              ),
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'class',
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'ISO 8601 datetime string (supports timezone, e.g. 2025-07-09T19:00:00+03:00)',
        'block' => false,
      ),
    ),
    'Upload' => 
    array (
      'kind' => 'ScalarTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Upload',
      ),
      'directives' => 
      array (
        0 => 
        array (
          'kind' => 'Directive',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'scalar',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'Argument',
              'value' => 
              array (
                'kind' => 'StringValue',
                'value' => 'Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\Upload',
                'block' => false,
              ),
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'class',
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Scalar for file uploads via multipart/form-data',
        'block' => false,
      ),
    ),
    'Query' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Query',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'user',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'ID',
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'eq',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'rules',
                  ),
                  'arguments' => 
                  array (
                    0 => 
                    array (
                      'kind' => 'Argument',
                      'value' => 
                      array (
                        'kind' => 'ListValue',
                        'values' => 
                        array (
                          0 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'prohibits:email',
                            'block' => false,
                          ),
                          1 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'required_without:email',
                            'block' => false,
                          ),
                        ),
                      ),
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'apply',
                      ),
                    ),
                  ),
                ),
              ),
              'description' => 
              array (
                'kind' => 'StringValue',
                'value' => 'Search by primary key.',
                'block' => false,
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'email',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'eq',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'rules',
                  ),
                  'arguments' => 
                  array (
                    0 => 
                    array (
                      'kind' => 'Argument',
                      'value' => 
                      array (
                        'kind' => 'ListValue',
                        'values' => 
                        array (
                          0 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'prohibits:id',
                            'block' => false,
                          ),
                          1 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'required_without:id',
                            'block' => false,
                          ),
                          2 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'email',
                            'block' => false,
                          ),
                        ),
                      ),
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'apply',
                      ),
                    ),
                  ),
                ),
              ),
              'description' => 
              array (
                'kind' => 'StringValue',
                'value' => 'Search by email address.',
                'block' => false,
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'User',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'find',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Find a single user by an identifying attribute.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'searchHotelRooms',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'HotelSearchInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'HotelSearchResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\SearchHotelRooms',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Search for hotel rooms with the cheapest price and prebook details.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'searchTBOHotelRooms',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'HotelSearchInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'TBOHotelSearchResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\SearchTBOHotelRooms',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Search for TBO hotel rooms with the cheapest price and prebook details.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'getCities',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'country_code',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
              'description' => 
              array (
                'kind' => 'StringValue',
                'value' => 'ISO 3166-1 alpha-2 country code (e.g. AE for United Arab Emirates, KW for Kuwait).',
                'block' => false,
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'GetCitiesResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\DotwGetCities',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List cities served by DOTW for a given country. Use city codes returned here as the destination input to searchHotels.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'searchHotels',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'SearchHotelsInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'SearchHotelsResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\DotwSearchHotels',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Search hotels by destination, dates, and room configuration via DOTW V4 searchhotels API. Results cached 2.5 minutes per company.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'getRoomRates',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'GetRoomRatesInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'GetRoomRatesResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\DotwGetRoomRates',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get detailed room rates for a specific hotel.

Returns all room types and meal plans with full cancellation policies,
allocationDetails tokens (required for blockRates), and transparent markup breakdown.

NOTE: DOTW getRooms command does not return hotel metadata (name, city, star rating,
image_url). These fields remain deferred (SEARCH-06 partial). Pass hotel_code from
searchHotels to this query; the caller must maintain hotel name from their own context.

Audit logged to dotw_audit_logs. NOT cached — rates change minute-to-minute and
allocationDetails tokens expire. Always returns fresh data from DOTW API.',
            'block' => true,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'getBookingDetails',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'GetBookingDetailsInput',
                  ),
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'spread',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'GetBookingDetailsResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\DotwGetBookingDetails',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get full details of an existing DOTW booking by booking code.',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'searchBookings',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'SearchBookingsInput',
                  ),
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'spread',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'SearchBookingsResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\DotwSearchBookings',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Search DOTW bookings by date range and/or customer reference. At least one filter required.',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkCancellation',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'CheckCancellationInput',
                  ),
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'spread',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'CheckCancellationResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\DotwCheckCancellation',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check the cancellation charge for a confirmed DOTW booking without committing the cancellation.

Calls DOTW cancelbooking with confirm=no. Returns the penalty charge that will be applied
if cancelBooking is called. Use the returned charge value as penalty_applied in cancelBooking.

This is step 1 of the two-step DOTW cancellation flow (CANCEL-01).',
            'block' => true,
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'getAllCountries',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'GetAllCountriesResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\DotwGetAllCountries',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get all DOTW internal country codes. Use nationality/residenceCountry values from this list when building booking passenger details. (LOOKUP-01)',
            'block' => false,
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'getServingCountries',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'GetServingCountriesResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\DotwGetServingCountries',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get countries for which DOTW has hotel inventory. Use to populate destination country selection in search UI. (LOOKUP-02)',
            'block' => false,
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'getHotelClassifications',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'GetHotelClassificationsResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\DotwGetHotelClassifications',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get hotel star rating classification codes. Use as minRating/maxRating filter values in searchHotels. (LOOKUP-03)',
            'block' => false,
          ),
        ),
        12 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'getLocationIds',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'GetLocationIdsResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\DotwGetLocationIds',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get location filtering codes for hotel search. Use as location filters to narrow results to specific areas. (LOOKUP-04)',
            'block' => false,
          ),
        ),
        13 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'getAmenityIds',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'GetAmenityIdsResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\DotwGetAmenityIds',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get amenity, leisure, and business facility codes merged from three DOTW commands. Use as amenity filter values in searchHotels. (LOOKUP-05, LOOKUP-06, LOOKUP-07)',
            'block' => false,
          ),
        ),
        14 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'getPreferenceIds',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'GetPreferenceIdsResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\DotwGetPreferenceIds',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get hotel preference codes from DOTW. Use as preference filter values in hotel search requests. (LOOKUP-05 preference subset)',
            'block' => false,
          ),
        ),
        15 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'getChainIds',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'GetChainIdsResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\DotwGetChainIds',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get hotel chain affiliation codes from DOTW. Use to filter search results to specific hotel chains. (LOOKUP-07 chain subset)',
            'block' => false,
          ),
        ),
        16 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'users',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'name',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'where',
                  ),
                  'arguments' => 
                  array (
                    0 => 
                    array (
                      'kind' => 'Argument',
                      'value' => 
                      array (
                        'kind' => 'StringValue',
                        'value' => 'like',
                        'block' => false,
                      ),
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'operator',
                      ),
                    ),
                  ),
                ),
              ),
              'description' => 
              array (
                'kind' => 'StringValue',
                'value' => 'Filters by name. Accepts SQL LIKE wildcards `%` and `_`.',
                'block' => false,
              ),
            ),
            1 => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 51,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 34,
                  'end' => 39,
                ),
                'kind' => 'Name',
                'value' => 'first',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 41,
                  'end' => 45,
                ),
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'loc' => 
                  array (
                    'start' => 41,
                    'end' => 44,
                  ),
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 41,
                      'end' => 44,
                    ),
                    'kind' => 'Name',
                    'value' => 'Int',
                  ),
                ),
              ),
              'defaultValue' => 
              array (
                'loc' => 
                array (
                  'start' => 49,
                  'end' => 51,
                ),
                'kind' => 'IntValue',
                'value' => '10',
              ),
              'directives' => 
              array (
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 33,
                ),
                'kind' => 'StringValue',
                'value' => 'Limits number of fetched items.',
                'block' => false,
              ),
            ),
            2 => 
            array (
              'loc' => 
              array (
                'start' => 4,
                'end' => 61,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 52,
                  'end' => 56,
                ),
                'kind' => 'Name',
                'value' => 'page',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 58,
                  'end' => 61,
                ),
                'kind' => 'NamedType',
                'name' => 
                array (
                  'loc' => 
                  array (
                    'start' => 58,
                    'end' => 61,
                  ),
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'directives' => 
              array (
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 4,
                  'end' => 47,
                ),
                'kind' => 'StringValue',
                'value' => 'The offset from which items are returned.',
                'block' => false,
              ),
            ),
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 0,
              'end' => 14,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 13,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 13,
                ),
                'kind' => 'Name',
                'value' => 'UserPaginator',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'paginate',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'IntValue',
                    'value' => '10',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'defaultCount',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List multiple users.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Indicates what fields are available at the top level of a query operation.',
        'block' => false,
      ),
    ),
    'HotelSearchInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'HotelSearchInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'telephone',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Customer telephone number (used to identify agent/company)',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotel name to search (partial match supported) - Optional if hotelCode provided',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotelCode',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'TBO hotel code - Optional if hotel name provided',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'city',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'City name to narrow down search (optional, partial match supported)',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'guestNationality',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Guest nationality code (ISO 3166-1 alpha-2, e.g., \'AL\', \'US\', \'GB\') - Required for TBO',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkIn',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Date',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-in date (format: YYYY-MM-DD)',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkOut',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Date',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-out date (format: YYYY-MM-DD)',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'roomCount',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'defaultValue' => 
          array (
            'kind' => 'IntValue',
            'value' => '1',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of cheapest rooms to prebook (default: 1)',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'nonRefundable',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Boolean',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Filter rooms by refundable type — true = non-refundable only, false = refundable only',
            'block' => false,
          ),
        ),
        9 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'boardBasis',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Filter by board basis codes (e.g., RO, BB, HB)',
            'block' => false,
          ),
        ),
        10 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'occupancy',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Mixed',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Occupancy configuration - Supports both string format \'2,1|1,0\' and array format [{adults: 2, children: 1}]',
            'block' => false,
          ),
        ),
        11 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'roomName',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Room Name to search for (partial match supported)',
            'block' => false,
          ),
        ),
        12 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'nationality',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Country name for nationality (e.g., \'Kuwait\', \'Saudi Arabia\', defaults to \'Kuwait\') - For Magic Holiday',
            'block' => false,
          ),
        ),
        13 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'noOfRooms',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of rooms to return - For TBO API (optional, defaults to 1)',
            'block' => false,
          ),
        ),
        14 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'refundable',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Boolean',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Filter by refundable type - For TBO API (optional)',
            'block' => false,
          ),
        ),
        15 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'mealType',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Meal type filter - For TBO API (optional, values: \'All\', \'WithMeal\', \'RoomOnly\', defaults to \'All\')',
            'block' => false,
          ),
        ),
        16 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'priceMin',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Float',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Minimum price filter - For TBO API (optional, applied locally after API response)',
            'block' => false,
          ),
        ),
        17 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'priceMax',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Float',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Maximum price filter - For TBO API (optional, applied locally after API response)',
            'block' => false,
          ),
        ),
        18 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'bookingType',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Booking type - determines pricing and markup (required for TBO)',
            'block' => false,
          ),
        ),
        19 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'minRating',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Minimum hotel rating filter (optional, e.g., 3 means 3-star and above)',
            'block' => false,
          ),
        ),
        20 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'maxRating',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Maximum hotel rating filter (optional, e.g., 4 means up to 4-star)',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Input for searching hotel rooms (unified for both Magic Holiday and TBO)',
        'block' => false,
      ),
    ),
    'HotelSearchResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'HotelSearchResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the search was successful',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'message',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Message describing the result',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'HotelSearchData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Search result data (null if unsuccessful)',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response for hotel room search',
        'block' => false,
      ),
    ),
    'HotelSearchData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'HotelSearchData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'telephone',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Customer telephone',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotel name',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'room_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total rooms returned',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rooms',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'RoomResult',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of cheapest rooms with details and prebook info',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'additional_info',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Additional info',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Hotel search result data',
        'block' => false,
      ),
    ),
    'RoomResult' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'RoomResult',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'room',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'RoomDetails',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'prebook',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'PrebookDetails',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Room details with prebook information',
        'block' => false,
      ),
    ),
    'RoomDetails' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'RoomDetails',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'room_name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Room name/type',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'board_basis',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Board basis (e.g., BB, HB, FB, AI)',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'non_refundable',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the room is non-refundable',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'price',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Price',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currency',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Currency code',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'info',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Room info',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'occupancy',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Occupancy details (JSON array)',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Room details',
        'block' => false,
      ),
    ),
    'PrebookDetails' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PrebookDetails',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'prebookKey',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'serviceDates',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'package',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'paymentMethods',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'bookingOptions',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'autocancelDate',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cancelPolicy',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'priceBreakdown',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'remarks',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'taxes',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Pre-booking details',
        'block' => false,
      ),
    ),
    'User' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'User',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Unique primary key.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Non-unique name.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'email',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Unique email address.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'email_verified_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DateTime',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'When the email was verified.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'When the account was created.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'When the account was last updated.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Account of a person who uses this application.',
        'block' => false,
      ),
    ),
    'Mutation' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Mutation',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'storePrebook',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'StorePrebookInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'StorePrebookResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\StorePrebook',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Stores magic holiday prebook data including availability, cancellation policies, and additional remarks.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'createFullB2CBooking',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'FullB2CBookingInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'FullB2CBookingResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\CreateFullB2CBooking',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'getFilteredHotels',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'GetFilteredHotelsInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'GetHotelsByCityPayload',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\GetFilteredHotels',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'B2BHotelSearchWithPrebook',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'B2BHotelSearchWithPrebookInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'B2BHotelSearchWithPrebookResult',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\B2BHotelSearchWithPrebook',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'blockRates',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'BlockRatesInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'BlockRatesResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\DotwBlockRates',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Lock a selected hotel rate for 3 minutes via DOTW blocking.

Calls DOTW getRooms with blocking=true to reserve the rate, then creates a
dotw_prebooks record. Returns prebook_key (UUID) and countdown_timer_seconds.

BLOCK-08: A new blockRates call from the same (company, resayil_message_id) pair
automatically expires any previous active prebook — only one active prebook per
WhatsApp conversation at any time.

Rejects if allocation is < 60 seconds from expiry — prompt re-search in that case.',
            'block' => true,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'createPreBooking',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'CreatePreBookingInput',
                  ),
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'spread',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'CreatePreBookingResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\DotwCreatePreBooking',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Confirm a hotel booking using a locked prebook_key from blockRates.
Validates passenger details, calls DOTW confirmBooking, creates dotw_bookings record.
Returns confirmation code and itinerary on success, or specific error with alternatives on failure.',
            'block' => true,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'saveBooking',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'SaveBookingInput',
                  ),
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'spread',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'SaveBookingResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\DotwSaveBooking',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Save a hotel booking as an itinerary for APR (non-refundable) rates. Returns itinerary_code to pass to bookItinerary.',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'bookItinerary',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'BookItineraryInput',
                  ),
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'spread',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'BookItineraryResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\DotwBookItinerary',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Confirm a previously saved itinerary (from saveBooking). Completes the APR booking flow.',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cancelBooking',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'CancelBookingInput',
                  ),
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'spread',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'CancelBookingResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\DotwCancelBooking',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Confirm cancellation of a DOTW hotel booking with the penalty amount from checkCancellation.

Calls DOTW cancelbooking with confirm=yes and penaltyApplied. APR (non-refundable) bookings
are rejected before any DOTW call is made (VALID-02). Returns cancelled=true and
products_left_on_itinerary on success.

This is step 2 of the two-step DOTW cancellation flow (CANCEL-02).',
            'block' => true,
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'deleteItinerary',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'DeleteItineraryInput',
                  ),
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'spread',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DeleteItineraryResponse',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\DotwDeleteItinerary',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Delete a saved (unconfirmed) itinerary from the APR flow.

Calls DOTW deleteitinerary command to remove an itinerary created by saveBooking
that has not yet been confirmed via bookItinerary. Only applicable for APR bookings.
Cannot delete confirmed bookings — use cancelBooking for those (CANCEL-03).',
            'block' => true,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Indicates what fields are available for mutations.',
        'block' => false,
      ),
    ),
    'StorePrebookInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'StorePrebookInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'telephone',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'availability_token',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'srk',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'package_token',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_id',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'offer_index',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'result_token',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rooms',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'PrebookRoomInput',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkin',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Date',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        9 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkout',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Date',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        10 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'duration',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        11 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'autocancel_date',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ISODateTime',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        12 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cancel_policy',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        13 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'remarks',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        14 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'service_dates',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        15 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'package',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        16 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'payment_methods',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        17 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'booking_options',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        18 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'price_breakdown',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        19 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'taxes',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Input data for storing a prebook details',
        'block' => false,
      ),
    ),
    'PrebookRoomInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PrebookRoomInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'room_token',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'room_name',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'board_basis',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'non_refundable',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Boolean',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'price',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Float',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currency',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'occupancy',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Room details input for prebook storage',
        'block' => false,
      ),
    ),
    'StorePrebookResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'StorePrebookResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'prebook_key',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'prebooking_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'message',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response for storing a prebook',
        'block' => false,
      ),
    ),
    'Price' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Price',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'value',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Float',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currency',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'BoardBasis' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'BoardBasis',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'price',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Price',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'nonRefundable' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'nonRefundable',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'price',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Price',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'GetHotelsByCityPayload' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetHotelsByCityPayload',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'message',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotels',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Hotel',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'OccupancyInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'OccupancyInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'leaderNationality',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rooms',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'RoomInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'RoomInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'adults',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'childrenAges',
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'PriceInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PriceInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'value',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Float',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currency',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'Filters' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Filters',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'classification',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'minPrice',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'PriceInput',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'maxPrice',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'PriceInput',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'DestinationInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DestinationInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'city',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'CityInput',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'CityInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'CityInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'GetFilteredHotelsInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'GetFilteredHotelsInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'destination',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DestinationInput',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkin',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkout',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'occupancy',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'OccupancyInput',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'sellingChannel',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'language',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'timeout',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'filters',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Filters',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'Hotel' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Hotel',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'index',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'address',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'stars',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'recommended',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Boolean',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'specialDeal',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Boolean',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'price',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Price',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'boardBasis',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'BoardBasis',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'nonRefundable',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'BoardBasis',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rateTags',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'FullB2CBookingInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'FullB2CBookingInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'phone',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'payment_gateway',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'payment_method',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'email',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'passport',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Upload',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'prebookKey',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'first_name',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'middle_name',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'last_name',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'PaymentMethodOption' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PaymentMethodOption',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'PaymentGatewayOption' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PaymentGatewayOption',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'type',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'methods',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'PaymentMethodOption',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'FullB2CBookingResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'FullB2CBookingResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'message',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'next_step',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'client_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'available_gateways',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'PaymentGatewayOption',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'room_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total_price',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Float',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currency',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'payment_link',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rooms',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'RoomResult',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'B2BHotelSearchWithPrebookInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'B2BHotelSearchWithPrebookInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'telephone',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'city',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkIn',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Date',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkOut',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Date',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'occupancy',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Mixed',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'nonRefundable',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Boolean',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'roomCount',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'boardBasis',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        9 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'minPrice',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'PriceInput',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        10 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'maxPrice',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'PriceInput',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        11 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'stars',
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        12 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'roomName',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        13 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'nationality',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'AgentInfo' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'AgentInfo',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'agentName',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'email',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'B2BMultipleHotelMatch' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'B2BMultipleHotelMatch',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'agentInfo',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'AgentInfo',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'message',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotels',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'B2BMatchedHotel',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'B2BMatchedHotel' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'B2BMatchedHotel',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'address',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'stars',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'index',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'B2BHotelSearchSuccess' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'B2BHotelSearchSuccess',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'agentInfo',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'AgentInfo',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'searchResult',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'HotelSearchResponse',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'B2BHotelSearchWithPrebookResult' => 
    array (
      'kind' => 'UnionTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'B2BHotelSearchWithPrebookResult',
      ),
      'directives' => 
      array (
      ),
      'types' => 
      array (
        0 => 
        array (
          'kind' => 'NamedType',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'B2BMultipleHotelMatch',
          ),
        ),
        1 => 
        array (
          'kind' => 'NamedType',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'B2BHotelSearchSuccess',
          ),
        ),
      ),
    ),
    'TBOHotelSearchInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'TBOHotelSearchInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotelCode',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotel code from TBO system',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'guestNationality',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Guest nationality code (ISO 3166-1 alpha-2)',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkIn',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Date',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-in date (format: YYYY-MM-DD)',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'checkOut',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Date',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Check-out date (format: YYYY-MM-DD)',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rooms',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'TBORoomInput',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Array of room configurations',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Input for searching TBO hotel rooms',
        'block' => false,
      ),
    ),
    'TBORoomInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'TBORoomInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'adults',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of adults',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'children',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of children',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'childAges',
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Ages of children (optional)',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Room configuration for TBO search',
        'block' => false,
      ),
    ),
    'TBOHotelSearchResponse' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'TBOHotelSearchResponse',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Whether the search was successful',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Status of the response: \'hotel_found\', \'multiple_hotels_found\', \'hotel_not_found\'',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'message',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Message describing the result',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'TBOHotelSearchData',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Search result data (null if unsuccessful or multiple hotels found)',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotelOptions',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'HotelOption',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of hotel options when multiple hotels are found',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Response for TBO hotel room search',
        'block' => false,
      ),
    ),
    'TBOHotelSearchData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'TBOHotelSearchData',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_code',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotel code',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotel name',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'room_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Total rooms returned',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rooms',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'TBORoomResult',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of rooms with details and prebook info',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'TBO hotel search result data',
        'block' => false,
      ),
    ),
    'TBORoomResult' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'TBORoomResult',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'success',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'error',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'room',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'RoomDetails',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'prebook',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'TBOPrebookDetails',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'TBO room details with prebook information',
        'block' => false,
      ),
    ),
    'TBOPrebookDetails' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'TBOPrebookDetails',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'prebookKey',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'tboId',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'bookingCode',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'serviceDates',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'package',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Mixed',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'totalFare',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Float',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'totalTax',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Float',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currency',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'mealType',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'isRefundable',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Boolean',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'inclusion',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cancelPolicies',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Mixed',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        12 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'amenities',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        13 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'dayRates',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Mixed',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        14 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rateConditions',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'TBO pre-booking details',
        'block' => false,
      ),
    ),
    'HotelOption' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'HotelOption',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'TBO Hotel Code (use this as hotelCode in next request)',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotel name from TBO',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'address',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotel address',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rating',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Hotel rating (e.g., \'ThreeStar\', \'FourStar\', \'FiveStar\')',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'city_name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'City name',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Hotel option when multiple hotels match the search',
        'block' => false,
      ),
    ),
    'PaginatorInfo' => 
    array (
      'loc' => 
      array (
        'start' => 4,
        'end' => 625,
      ),
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 78,
          'end' => 91,
        ),
        'kind' => 'Name',
        'value' => 'PaginatorInfo',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 101,
            'end' => 157,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 146,
              'end' => 151,
            ),
            'kind' => 'Name',
            'value' => 'count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 153,
              'end' => 157,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 153,
                'end' => 156,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 153,
                  'end' => 156,
                ),
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 101,
              'end' => 139,
            ),
            'kind' => 'StringValue',
            'value' => 'Number of items in the current page.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 165,
            'end' => 217,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 200,
              'end' => 211,
            ),
            'kind' => 'Name',
            'value' => 'currentPage',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 213,
              'end' => 217,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 213,
                'end' => 216,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 213,
                  'end' => 216,
                ),
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 165,
              'end' => 193,
            ),
            'kind' => 'StringValue',
            'value' => 'Index of the current page.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'loc' => 
          array (
            'start' => 225,
            'end' => 292,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 278,
              'end' => 287,
            ),
            'kind' => 'Name',
            'value' => 'firstItem',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 289,
              'end' => 292,
            ),
            'kind' => 'NamedType',
            'name' => 
            array (
              'loc' => 
              array (
                'start' => 289,
                'end' => 292,
              ),
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 225,
              'end' => 271,
            ),
            'kind' => 'StringValue',
            'value' => 'Index of the first item in the current page.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'loc' => 
          array (
            'start' => 300,
            'end' => 367,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 345,
              'end' => 357,
            ),
            'kind' => 'Name',
            'value' => 'hasMorePages',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 359,
              'end' => 367,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 359,
                'end' => 366,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 359,
                  'end' => 366,
                ),
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 300,
              'end' => 338,
            ),
            'kind' => 'StringValue',
            'value' => 'Are there more pages after this one?',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'loc' => 
          array (
            'start' => 375,
            'end' => 440,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 427,
              'end' => 435,
            ),
            'kind' => 'Name',
            'value' => 'lastItem',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 437,
              'end' => 440,
            ),
            'kind' => 'NamedType',
            'name' => 
            array (
              'loc' => 
              array (
                'start' => 437,
                'end' => 440,
              ),
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 375,
              'end' => 420,
            ),
            'kind' => 'StringValue',
            'value' => 'Index of the last item in the current page.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'loc' => 
          array (
            'start' => 448,
            'end' => 504,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 490,
              'end' => 498,
            ),
            'kind' => 'Name',
            'value' => 'lastPage',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 500,
              'end' => 504,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 500,
                'end' => 503,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 500,
                  'end' => 503,
                ),
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 448,
              'end' => 483,
            ),
            'kind' => 'StringValue',
            'value' => 'Index of the last available page.',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'loc' => 
          array (
            'start' => 512,
            'end' => 559,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 546,
              'end' => 553,
            ),
            'kind' => 'Name',
            'value' => 'perPage',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 555,
              'end' => 559,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 555,
                'end' => 558,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 555,
                  'end' => 558,
                ),
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 512,
              'end' => 539,
            ),
            'kind' => 'StringValue',
            'value' => 'Number of items per page.',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'loc' => 
          array (
            'start' => 567,
            'end' => 619,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 608,
              'end' => 613,
            ),
            'kind' => 'Name',
            'value' => 'total',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 615,
              'end' => 619,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 615,
                'end' => 618,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 615,
                  'end' => 618,
                ),
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 567,
              'end' => 601,
            ),
            'kind' => 'StringValue',
            'value' => 'Number of total available items.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 4,
          'end' => 68,
        ),
        'kind' => 'StringValue',
        'value' => 'Information about pagination using a fully featured paginator.',
        'block' => false,
      ),
    ),
    'UserPaginator' => 
    array (
      'loc' => 
      array (
        'start' => 4,
        'end' => 391,
      ),
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 47,
          'end' => 60,
        ),
        'kind' => 'Name',
        'value' => 'UserPaginator',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 4,
            'end' => 38,
          ),
          'kind' => 'Directive',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 5,
              'end' => 10,
            ),
            'kind' => 'Name',
            'value' => 'model',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 11,
                'end' => 37,
              ),
              'kind' => 'Argument',
              'value' => 
              array (
                'loc' => 
                array (
                  'start' => 18,
                  'end' => 37,
                ),
                'kind' => 'StringValue',
                'value' => 'App\\Models\\User',
                'block' => false,
              ),
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 11,
                  'end' => 16,
                ),
                'kind' => 'Name',
                'value' => 'class',
              ),
            ),
          ),
        ),
      ),
      'fields' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 71,
            'end' => 247,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 129,
              'end' => 142,
            ),
            'kind' => 'Name',
            'value' => 'paginatorInfo',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 144,
              'end' => 158,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 144,
                'end' => 157,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 144,
                  'end' => 157,
                ),
                'kind' => 'Name',
                'value' => 'PaginatorInfo',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 159,
                'end' => 247,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 160,
                  'end' => 165,
                ),
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 166,
                    'end' => 246,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 176,
                      'end' => 246,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'Nuwave\\Lighthouse\\Pagination\\PaginatorField@paginatorInfoResolver',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 166,
                      'end' => 174,
                    ),
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 71,
              'end' => 120,
            ),
            'kind' => 'StringValue',
            'value' => 'Pagination information about the list of items.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 258,
            'end' => 384,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 290,
              'end' => 294,
            ),
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 296,
              'end' => 304,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 296,
                'end' => 303,
              ),
              'kind' => 'ListType',
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 297,
                  'end' => 302,
                ),
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'loc' => 
                  array (
                    'start' => 297,
                    'end' => 301,
                  ),
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 297,
                      'end' => 301,
                    ),
                    'kind' => 'Name',
                    'value' => 'User',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 305,
                'end' => 384,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 306,
                  'end' => 311,
                ),
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 312,
                    'end' => 383,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 322,
                      'end' => 383,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'Nuwave\\Lighthouse\\Pagination\\PaginatorField@dataResolver',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 312,
                      'end' => 320,
                    ),
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 258,
              'end' => 281,
            ),
            'kind' => 'StringValue',
            'value' => 'A list of User items.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 4,
          'end' => 37,
        ),
        'kind' => 'StringValue',
        'value' => 'A paginated list of User items.',
        'block' => false,
      ),
    ),
    'SortOrder' => 
    array (
      'loc' => 
      array (
        'start' => 21,
        'end' => 301,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 91,
          'end' => 100,
        ),
        'kind' => 'Name',
        'value' => 'SortOrder',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 127,
            'end' => 189,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 186,
              'end' => 189,
            ),
            'kind' => 'Name',
            'value' => 'ASC',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 127,
              'end' => 161,
            ),
            'kind' => 'StringValue',
            'value' => 'Sort records in ascending order.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 215,
            'end' => 279,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 275,
              'end' => 279,
            ),
            'kind' => 'Name',
            'value' => 'DESC',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 215,
              'end' => 250,
            ),
            'kind' => 'StringValue',
            'value' => 'Sort records in descending order.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 21,
          'end' => 65,
        ),
        'kind' => 'StringValue',
        'value' => 'Directions for ordering a list of records.',
        'block' => false,
      ),
    ),
    'OrderByRelationAggregateFunction' => 
    array (
      'loc' => 
      array (
        'start' => 21,
        'end' => 276,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 125,
          'end' => 157,
        ),
        'kind' => 'Name',
        'value' => 'OrderByRelationAggregateFunction',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 184,
            'end' => 254,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 227,
              'end' => 232,
            ),
            'kind' => 'Name',
            'value' => 'COUNT',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 233,
                'end' => 254,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 234,
                  'end' => 238,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 239,
                    'end' => 253,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 246,
                      'end' => 253,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'count',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 239,
                      'end' => 244,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 184,
              'end' => 202,
            ),
            'kind' => 'StringValue',
            'value' => 'Amount of items.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 21,
          'end' => 99,
        ),
        'kind' => 'StringValue',
        'value' => 'Aggregate functions when ordering by a relation without specifying a column.',
        'block' => false,
      ),
    ),
    'OrderByRelationWithColumnAggregateFunction' => 
    array (
      'loc' => 
      array (
        'start' => 21,
        'end' => 616,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 123,
          'end' => 165,
        ),
        'kind' => 'Name',
        'value' => 'OrderByRelationWithColumnAggregateFunction',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 192,
            'end' => 250,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 227,
              'end' => 230,
            ),
            'kind' => 'Name',
            'value' => 'AVG',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 231,
                'end' => 250,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 232,
                  'end' => 236,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 237,
                    'end' => 249,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 244,
                      'end' => 249,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'avg',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 237,
                      'end' => 242,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 192,
              'end' => 202,
            ),
            'kind' => 'StringValue',
            'value' => 'Average.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 276,
            'end' => 334,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 311,
              'end' => 314,
            ),
            'kind' => 'Name',
            'value' => 'MIN',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 315,
                'end' => 334,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 316,
                  'end' => 320,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 321,
                    'end' => 333,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 328,
                      'end' => 333,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'min',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 321,
                      'end' => 326,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 276,
              'end' => 286,
            ),
            'kind' => 'StringValue',
            'value' => 'Minimum.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'loc' => 
          array (
            'start' => 360,
            'end' => 418,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 395,
              'end' => 398,
            ),
            'kind' => 'Name',
            'value' => 'MAX',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 399,
                'end' => 418,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 400,
                  'end' => 404,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 405,
                    'end' => 417,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 412,
                      'end' => 417,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'max',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 405,
                      'end' => 410,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 360,
              'end' => 370,
            ),
            'kind' => 'StringValue',
            'value' => 'Maximum.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'loc' => 
          array (
            'start' => 444,
            'end' => 498,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 475,
              'end' => 478,
            ),
            'kind' => 'Name',
            'value' => 'SUM',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 479,
                'end' => 498,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 480,
                  'end' => 484,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 485,
                    'end' => 497,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 492,
                      'end' => 497,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'sum',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 485,
                      'end' => 490,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 444,
              'end' => 450,
            ),
            'kind' => 'StringValue',
            'value' => 'Sum.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'loc' => 
          array (
            'start' => 524,
            'end' => 594,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 567,
              'end' => 572,
            ),
            'kind' => 'Name',
            'value' => 'COUNT',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 573,
                'end' => 594,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 574,
                  'end' => 578,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 579,
                    'end' => 593,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 586,
                      'end' => 593,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'count',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 579,
                      'end' => 584,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 524,
              'end' => 542,
            ),
            'kind' => 'StringValue',
            'value' => 'Amount of items.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 21,
          'end' => 97,
        ),
        'kind' => 'StringValue',
        'value' => 'Aggregate functions when ordering by a relation that may specify a column.',
        'block' => false,
      ),
    ),
    'OrderByClause' => 
    array (
      'loc' => 
      array (
        'start' => 12,
        'end' => 278,
      ),
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 67,
          'end' => 80,
        ),
        'kind' => 'Name',
        'value' => 'OrderByClause',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 99,
            'end' => 170,
          ),
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 155,
              'end' => 161,
            ),
            'kind' => 'Name',
            'value' => 'column',
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 163,
              'end' => 170,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 163,
                'end' => 169,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 163,
                  'end' => 169,
                ),
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 99,
              'end' => 138,
            ),
            'kind' => 'StringValue',
            'value' => 'The column that is used for ordering.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 188,
            'end' => 264,
          ),
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 247,
              'end' => 252,
            ),
            'kind' => 'Name',
            'value' => 'order',
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 254,
              'end' => 264,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 254,
                'end' => 263,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 254,
                  'end' => 263,
                ),
                'kind' => 'Name',
                'value' => 'SortOrder',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 188,
              'end' => 230,
            ),
            'kind' => 'StringValue',
            'value' => 'The direction that is used for ordering.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 12,
          'end' => 48,
        ),
        'kind' => 'StringValue',
        'value' => 'Allows ordering a list of records.',
        'block' => false,
      ),
    ),
    'Trashed' => 
    array (
      'loc' => 
      array (
        'start' => 25,
        'end' => 530,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 128,
          'end' => 135,
        ),
        'kind' => 'Name',
        'value' => 'Trashed',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 166,
            'end' => 250,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 225,
              'end' => 229,
            ),
            'kind' => 'Name',
            'value' => 'ONLY',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 230,
                'end' => 250,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 231,
                  'end' => 235,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 236,
                    'end' => 249,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 243,
                      'end' => 249,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'only',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 236,
                      'end' => 241,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 166,
              'end' => 196,
            ),
            'kind' => 'StringValue',
            'value' => 'Only return trashed results.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 280,
            'end' => 380,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 355,
              'end' => 359,
            ),
            'kind' => 'Name',
            'value' => 'WITH',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 360,
                'end' => 380,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 361,
                  'end' => 365,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 366,
                    'end' => 379,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 373,
                      'end' => 379,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'with',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 366,
                      'end' => 371,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 280,
              'end' => 326,
            ),
            'kind' => 'StringValue',
            'value' => 'Return both trashed and non-trashed results.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'loc' => 
          array (
            'start' => 410,
            'end' => 504,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 473,
              'end' => 480,
            ),
            'kind' => 'Name',
            'value' => 'WITHOUT',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 481,
                'end' => 504,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 482,
                  'end' => 486,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 487,
                    'end' => 503,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 494,
                      'end' => 503,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'without',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 487,
                      'end' => 492,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 410,
              'end' => 444,
            ),
            'kind' => 'StringValue',
            'value' => 'Only return non-trashed results.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 25,
          'end' => 98,
        ),
        'kind' => 'StringValue',
        'value' => 'Specify if you want to include or exclude trashed results from a query.',
        'block' => false,
      ),
    ),
  ),
  'directives' => 
  array (
  ),
  'classNameToObjectTypeName' => 
  array (
  ),
  'schemaExtensions' => 
  array (
  ),
  'hash' => '44901b6083548f47b8040581db8ca99dbf1609ba3db820acc3f63e9983bf0dd3',
);