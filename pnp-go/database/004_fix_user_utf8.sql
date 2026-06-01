USE carrequest;

UPDATE users
SET full_name = CONVERT(UNHEX('e0b899e0b8b2e0b8a2e0b8a7e0b8b4e0b899e0b8b1e0b8a220e0b888e0b8b1e0b899e0b897e0b8a3e0b98ce0b884e0b8b3') USING utf8mb4),
    position_title = CONVERT(UNHEX('e0b8abe0b8b1e0b8a7e0b8abe0b899e0b989e0b8b2e0b887e0b8b2e0b899e0b89ee0b8b1e0b8aae0b894e0b8b8') USING utf8mb4)
WHERE username = 'supply';

UPDATE users
SET full_name = CONVERT(UNHEX('e0b899e0b8b2e0b8a2e0b8a1e0b893e0b980e0b891e0b8b5e0b8a2e0b8a320e0b887e0b8b2e0b8a1e0b981e0b881e0b989e0b8a7') USING utf8mb4),
    position_title = CONVERT(UNHEX('e0b8a3e0b8ade0b887e0b89ce0b8b9e0b989e0b8ade0b8b3e0b899e0b8a7e0b8a2e0b881e0b8b2e0b8a3e0b89de0b988e0b8b2e0b8a2e0b89ae0b8a3e0b8b4e0b8abe0b8b2e0b8a3e0b897e0b8a3e0b8b1e0b89ee0b8a2e0b8b2e0b881e0b8a3') USING utf8mb4)
WHERE username = 'deputy';

UPDATE users
SET full_name = CONVERT(UNHEX('e0b899e0b8b2e0b8a2e0b89ae0b8b1e0b88de0b88ae0b8b220e0b982e0b884e0b895e0b8a3e0b981e0b881e0b989e0b8a7') USING utf8mb4),
    position_title = CONVERT(UNHEX('e0b89ce0b8b9e0b989e0b8ade0b8b3e0b899e0b8a7e0b8a2e0b881e0b8b2e0b8a3') USING utf8mb4)
WHERE username = 'director';

UPDATE users
SET full_name = CONVERT(UNHEX('e0b89ce0b8b9e0b989e0b894e0b8b9e0b981e0b8a5e0b8a3e0b8b0e0b89ae0b89a') USING utf8mb4),
    position_title = CONVERT(UNHEX('e0b89ce0b8b9e0b989e0b894e0b8b9e0b981e0b8a5e0b8a3e0b8b0e0b89ae0b89a') USING utf8mb4)
WHERE username = 'admin';
