USE carrequest;

UPDATE vehicles
SET vehicle_name = CONVERT(UNHEX('e0b982e0b895e0b982e0b8a2e0b895e0b989e0b8b220e0b8a3e0b896e0b895e0b8b9e0b989') USING utf8mb4),
    license_plate = CONVERT(UNHEX('3730393320e0b881e0b897e0b8a1') USING utf8mb4),
    vehicle_type = CONVERT(UNHEX('e0b8a3e0b896e0b895e0b8b9e0b989') USING utf8mb4)
WHERE id = 1;

UPDATE vehicles
SET vehicle_name = CONVERT(UNHEX('e0b982e0b895e0b982e0b8a2e0b895e0b989e0b8b220e0b8a3e0b896e0b882e0b8b2e0b8a7') USING utf8mb4),
    license_plate = CONVERT(UNHEX('e0b881e0b88234353938') USING utf8mb4),
    vehicle_type = CONVERT(UNHEX('e0b8a3e0b896e0b882e0b8b2e0b8a7') USING utf8mb4)
WHERE id = 2;

UPDATE vehicles
SET vehicle_name = CONVERT(UNHEX('e0b8ade0b8b5e0b88be0b8b9e0b88be0b8b820e0b8a3e0b896e0b980e0b8abe0b8a5e0b8b7e0b8ade0b88720e0b983e0b8abe0b8a1e0b988') USING utf8mb4),
    license_plate = CONVERT(UNHEX('34302d30343132') USING utf8mb4),
    vehicle_type = CONVERT(UNHEX('e0b8a3e0b896e0b8abe0b881e0b8a5e0b989e0b8ad') USING utf8mb4)
WHERE id = 3;

UPDATE vehicles
SET vehicle_name = CONVERT(UNHEX('e0b982e0b895e0b982e0b8a2e0b895e0b989e0b8b220e0b8a3e0b896e0b8aae0b8b5e0b988e0b89be0b8a3e0b8b0e0b895e0b8b920e0b983e0b8abe0b8a1e0b988') USING utf8mb4),
    license_plate = CONVERT(UNHEX('e0b881e0b89933383832') USING utf8mb4),
    vehicle_type = CONVERT(UNHEX('e0b8a3e0b896e0b8aae0b8b5e0b988e0b89be0b8a3e0b8b0e0b895e0b8b9') USING utf8mb4)
WHERE id = 4;

UPDATE vehicles
SET vehicle_name = CONVERT(UNHEX('e0b899e0b8b4e0b8aae0b8aae0b8b1e0b89920e0b8a3e0b896e0b897e0b8ade0b887') USING utf8mb4),
    license_plate = CONVERT(UNHEX('e0b89ae0b89b35303838') USING utf8mb4),
    vehicle_type = CONVERT(UNHEX('e0b8a3e0b896e0b897e0b8ade0b887') USING utf8mb4)
WHERE id = 5;

UPDATE vehicles
SET vehicle_name = CONVERT(UNHEX('e0b8ade0b8b5e0b88be0b8b9e0b88be0b8b820e0b8a3e0b896e0b8abe0b881e0b8a5e0b989e0b8ad20e0b980e0b881e0b988e0b8b2') USING utf8mb4),
    license_plate = CONVERT(UNHEX('34302d30313939') USING utf8mb4),
    vehicle_type = CONVERT(UNHEX('e0b8a3e0b896e0b8abe0b881e0b8a5e0b989e0b8ad') USING utf8mb4)
WHERE id = 6;
