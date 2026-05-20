<?php return array (
  'types' => 
  array (
    'DotwHotelSearchInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwHotelSearchInput',
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
        ),
        3 => 
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
        ),
        4 => 
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
                'value' => 'String',
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
                    'value' => 'DotwOccupancyInput',
                  ),
                ),
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
        ),
        8 => 
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
        ),
        9 => 
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
        ),
        10 => 
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
        ),
        11 => 
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
        ),
        12 => 
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
        ),
        13 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'starRating',
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
    'DotwOccupancyInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwOccupancyInput',
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
        ),
      ),
    ),
    'DotwSearchResult' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwSearchResult',
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
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DotwHotelOption',
              ),
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
              'value' => 'DotwHotelData',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'DotwHotelOption' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwHotelOption',
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
        ),
        2 => 
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
    'DotwHotelData' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwHotelData',
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
        ),
        1 => 
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
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hotel_address',
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
            'value' => 'star_rating',
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
        ),
        5 => 
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
                    'value' => 'DotwRoomResult',
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
    'DotwRoomResult' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwRoomResult',
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
        ),
        1 => 
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
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rate_basis_desc',
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
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meal_type',
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
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'max_occupancy',
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
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'twin',
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
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'browse_allocation_details',
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
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'displayed_price',
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
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'original_total_fare',
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
        ),
        10 => 
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
        ),
        11 => 
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
        ),
        12 => 
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
        ),
        13 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cancel_policies',
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
                'value' => 'DotwCancelPolicy',
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
            'value' => 'tariff_notes',
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
        15 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'specials',
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
                'value' => 'DotwSpecial',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        16 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'min_stay',
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
        17 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'min_stay_date',
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
    'DotwCancelPolicy' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwCancelPolicy',
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
            'value' => 'fromDate',
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
            'value' => 'toDate',
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
            'value' => 'chargeType',
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
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cancelRestricted',
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
            'value' => 'amendRestricted',
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
      ),
    ),
    'DotwSpecial' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DotwSpecial',
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
            'value' => 'type',
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
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'description',
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
            'value' => 'condition',
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
            'value' => 'searchDotwHotelRooms',
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
                    'value' => 'DotwHotelSearchInput',
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
                'value' => 'DotwSearchResult',
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
                    'value' => 'App\\Modules\\AkeedDotwAI\\GraphQL\\Queries\\SearchDotwHotelRooms',
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
                'end' => 62,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 53,
                  'end' => 57,
                ),
                'kind' => 'Name',
                'value' => 'page',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 59,
                  'end' => 62,
                ),
                'kind' => 'NamedType',
                'name' => 
                array (
                  'loc' => 
                  array (
                    'start' => 59,
                    'end' => 62,
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
        'end' => 650,
      ),
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 79,
          'end' => 92,
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
            'start' => 103,
            'end' => 160,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 149,
              'end' => 154,
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
              'start' => 156,
              'end' => 160,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 156,
                'end' => 159,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 156,
                  'end' => 159,
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
              'start' => 103,
              'end' => 141,
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
            'start' => 170,
            'end' => 223,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 206,
              'end' => 217,
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
              'start' => 219,
              'end' => 223,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 219,
                'end' => 222,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 219,
                  'end' => 222,
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
              'start' => 170,
              'end' => 198,
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
            'start' => 233,
            'end' => 301,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 287,
              'end' => 296,
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
              'start' => 298,
              'end' => 301,
            ),
            'kind' => 'NamedType',
            'name' => 
            array (
              'loc' => 
              array (
                'start' => 298,
                'end' => 301,
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
              'start' => 233,
              'end' => 279,
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
            'start' => 311,
            'end' => 379,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 357,
              'end' => 369,
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
              'start' => 371,
              'end' => 379,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 371,
                'end' => 378,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 371,
                  'end' => 378,
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
              'start' => 311,
              'end' => 349,
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
            'start' => 389,
            'end' => 455,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 442,
              'end' => 450,
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
              'start' => 452,
              'end' => 455,
            ),
            'kind' => 'NamedType',
            'name' => 
            array (
              'loc' => 
              array (
                'start' => 452,
                'end' => 455,
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
              'start' => 389,
              'end' => 434,
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
            'start' => 465,
            'end' => 522,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 508,
              'end' => 516,
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
              'start' => 518,
              'end' => 522,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 518,
                'end' => 521,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 518,
                  'end' => 521,
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
              'start' => 465,
              'end' => 500,
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
            'start' => 532,
            'end' => 580,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 567,
              'end' => 574,
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
              'start' => 576,
              'end' => 580,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 576,
                'end' => 579,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 576,
                  'end' => 579,
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
              'start' => 532,
              'end' => 559,
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
            'start' => 590,
            'end' => 643,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 632,
              'end' => 637,
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
              'start' => 639,
              'end' => 643,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 639,
                'end' => 642,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 639,
                  'end' => 642,
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
              'start' => 590,
              'end' => 624,
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
        'end' => 398,
      ),
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 48,
          'end' => 61,
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
            'start' => 73,
            'end' => 250,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 132,
              'end' => 145,
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
              'start' => 147,
              'end' => 161,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 147,
                'end' => 160,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 147,
                  'end' => 160,
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
                'start' => 162,
                'end' => 250,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 163,
                  'end' => 168,
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
                    'start' => 169,
                    'end' => 249,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 179,
                      'end' => 249,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'Nuwave\\Lighthouse\\Pagination\\PaginatorField@paginatorInfoResolver',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 169,
                      'end' => 177,
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
              'start' => 73,
              'end' => 122,
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
            'start' => 263,
            'end' => 390,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 296,
              'end' => 300,
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
              'start' => 302,
              'end' => 310,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 302,
                'end' => 309,
              ),
              'kind' => 'ListType',
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 303,
                  'end' => 308,
                ),
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'loc' => 
                  array (
                    'start' => 303,
                    'end' => 307,
                  ),
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 303,
                      'end' => 307,
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
                'start' => 311,
                'end' => 390,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 312,
                  'end' => 317,
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
                    'start' => 318,
                    'end' => 389,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 328,
                      'end' => 389,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'Nuwave\\Lighthouse\\Pagination\\PaginatorField@dataResolver',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 318,
                      'end' => 326,
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
              'start' => 263,
              'end' => 286,
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
        'start' => 22,
        'end' => 309,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 93,
          'end' => 102,
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
            'start' => 130,
            'end' => 193,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 190,
              'end' => 193,
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
              'start' => 130,
              'end' => 164,
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
            'start' => 221,
            'end' => 286,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 282,
              'end' => 286,
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
              'start' => 221,
              'end' => 256,
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
          'start' => 22,
          'end' => 66,
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
        'start' => 22,
        'end' => 281,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 127,
          'end' => 159,
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
            'start' => 187,
            'end' => 258,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 231,
              'end' => 236,
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
                'start' => 237,
                'end' => 258,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 238,
                  'end' => 242,
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
                    'start' => 243,
                    'end' => 257,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 250,
                      'end' => 257,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'count',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 243,
                      'end' => 248,
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
              'start' => 187,
              'end' => 205,
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
          'start' => 22,
          'end' => 100,
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
        'start' => 22,
        'end' => 633,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 125,
          'end' => 167,
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
            'start' => 195,
            'end' => 254,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 231,
              'end' => 234,
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
                'start' => 235,
                'end' => 254,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 236,
                  'end' => 240,
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
                    'start' => 241,
                    'end' => 253,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 248,
                      'end' => 253,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'avg',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 241,
                      'end' => 246,
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
              'start' => 195,
              'end' => 205,
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
            'start' => 282,
            'end' => 341,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 318,
              'end' => 321,
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
                'start' => 322,
                'end' => 341,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 323,
                  'end' => 327,
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
                    'start' => 328,
                    'end' => 340,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 335,
                      'end' => 340,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'min',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 328,
                      'end' => 333,
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
              'start' => 282,
              'end' => 292,
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
            'start' => 369,
            'end' => 428,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 405,
              'end' => 408,
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
                'start' => 409,
                'end' => 428,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 410,
                  'end' => 414,
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
                    'start' => 415,
                    'end' => 427,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 422,
                      'end' => 427,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'max',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 415,
                      'end' => 420,
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
              'start' => 369,
              'end' => 379,
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
            'start' => 456,
            'end' => 511,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 488,
              'end' => 491,
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
                'start' => 492,
                'end' => 511,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 493,
                  'end' => 497,
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
                    'start' => 498,
                    'end' => 510,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 505,
                      'end' => 510,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'sum',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 498,
                      'end' => 503,
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
              'start' => 456,
              'end' => 462,
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
            'start' => 539,
            'end' => 610,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 583,
              'end' => 588,
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
                'start' => 589,
                'end' => 610,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 590,
                  'end' => 594,
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
                    'start' => 595,
                    'end' => 609,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 602,
                      'end' => 609,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'count',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 595,
                      'end' => 600,
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
              'start' => 539,
              'end' => 557,
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
          'start' => 22,
          'end' => 98,
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
        'end' => 285,
      ),
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 68,
          'end' => 81,
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
            'start' => 101,
            'end' => 173,
          ),
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 158,
              'end' => 164,
            ),
            'kind' => 'Name',
            'value' => 'column',
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 166,
              'end' => 173,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 166,
                'end' => 172,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 166,
                  'end' => 172,
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
              'start' => 101,
              'end' => 140,
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
            'start' => 193,
            'end' => 270,
          ),
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 253,
              'end' => 258,
            ),
            'kind' => 'Name',
            'value' => 'order',
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 260,
              'end' => 270,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 260,
                'end' => 269,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 260,
                  'end' => 269,
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
              'start' => 193,
              'end' => 235,
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
        'start' => 26,
        'end' => 541,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 130,
          'end' => 137,
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
            'start' => 169,
            'end' => 254,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 229,
              'end' => 233,
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
                'start' => 234,
                'end' => 254,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 235,
                  'end' => 239,
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
                    'start' => 240,
                    'end' => 253,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 247,
                      'end' => 253,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'only',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 240,
                      'end' => 245,
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
              'start' => 169,
              'end' => 199,
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
            'start' => 286,
            'end' => 387,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 362,
              'end' => 366,
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
                'start' => 367,
                'end' => 387,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 368,
                  'end' => 372,
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
                    'start' => 373,
                    'end' => 386,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 380,
                      'end' => 386,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'with',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 373,
                      'end' => 378,
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
              'start' => 286,
              'end' => 332,
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
            'start' => 419,
            'end' => 514,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 483,
              'end' => 490,
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
                'start' => 491,
                'end' => 514,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 492,
                  'end' => 496,
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
                    'start' => 497,
                    'end' => 513,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 504,
                      'end' => 513,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'without',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 497,
                      'end' => 502,
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
              'start' => 419,
              'end' => 453,
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
          'start' => 26,
          'end' => 99,
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
  'hash' => '41d53cfce9cee61401d3692afe7a0b4a63f81852deccedf659cba167e4663f65',
);