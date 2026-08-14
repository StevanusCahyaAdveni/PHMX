CREATE TABLE `products` (
  `id` varchar(36) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `price` INT(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
