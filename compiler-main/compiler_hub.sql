-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 04, 2026 at 06:52 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `compiler_hub`
--

-- --------------------------------------------------------

--
-- Table structure for table `compilation_logs`
--

CREATE TABLE `compilation_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `language` varchar(20) NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `source_code_hash` varchar(64) NOT NULL,
  `source_code_preview` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `errors_count` int(11) DEFAULT 0,
  `compilation_time_ms` int(11) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `compilation_logs`
--

INSERT INTO `compilation_logs` (`id`, `user_id`, `language`, `session_id`, `source_code_hash`, `source_code_preview`, `success`, `errors_count`, `compilation_time_ms`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'brainfuck', 'brainfuck_compile_69cffb609da2f1.58612342', 'd57484433de2fb2d4e2d14930154cc200c2ff496299f1c53b4216dab31282e31', '++++++++++[>+++++++>++++++++++>+++>+<<<<-]\n>++.>+.+++++++..+++.>++.<<+++++++++++++++.>.+++.------.--------.>+.>.', 1, 0, 1189, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:39:44'),
(2, 1, 'brainfuck', 'brainfuck_compile_69d002c803ebb6.44471221', 'd57484433de2fb2d4e2d14930154cc200c2ff496299f1c53b4216dab31282e31', '++++++++++[>+++++++>++++++++++>+++>+<<<<-]\n>++.>+.+++++++..+++.>++.<<+++++++++++++++.>.+++.------.--------.>+.>.', 1, 0, 1347, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 18:11:20'),
(3, 1, 'brainfuck', 'brainfuck_compile_69d0034d2fb440.34085202', 'd57484433de2fb2d4e2d14930154cc200c2ff496299f1c53b4216dab31282e31', '++++++++++[>+++++++>++++++++++>+++>+<<<<-]\n>++.>+.+++++++..+++.>++.<<+++++++++++++++.>.+++.------.--------.>+.>.', 1, 0, 1164, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 18:13:33'),
(4, NULL, 'brainfuck', 'brainfuck_compile_69d00991b92c20.97553403', 'd57484433de2fb2d4e2d14930154cc200c2ff496299f1c53b4216dab31282e31', '++++++++++[>+++++++>++++++++++>+++>+<<<<-]\n>++.>+.+++++++..+++.>++.<<+++++++++++++++.>.+++.------.--------.>+.>.', 1, 0, 1227, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 18:40:17'),
(5, NULL, 'java', 'java_compile_69d01b6f022f62.02751671', 'afd9ae201949082c1d97fc4d0b8f122688453024a69af1c2d1d1a9f025f018f6', 'public class Main {\n    public static void main(String[] args) {\n        int x = 10;\n        int y = 20;\n        int result = x + y;\n        \n        System.out.println(\"Result: \" + result);\n        \n        for (int i = 0; i < 5; i++) {\n            System.out.println(\"Iteration \" + i);\n        }\n        \n        if (result > 25) {\n            System.out.println(\"Result is greater than 25\");\n        } else {\n            System.out.println(\"Result is 25 or less\");\n        }\n    }\n}', 1, 0, 1640, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 19:56:31'),
(6, 1, 'java', 'java_compile_69d022f7e969c2.29359670', 'afd9ae201949082c1d97fc4d0b8f122688453024a69af1c2d1d1a9f025f018f6', 'public class Main {\n    public static void main(String[] args) {\n        int x = 10;\n        int y = 20;\n        int result = x + y;\n        \n        System.out.println(\"Result: \" + result);\n        \n        for (int i = 0; i < 5; i++) {\n            System.out.println(\"Iteration \" + i);\n        }\n        \n        if (result > 25) {\n            System.out.println(\"Result is greater than 25\");\n        } else {\n            System.out.println(\"Result is 25 or less\");\n        }\n    }\n}', 1, 0, 1870, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:28:40'),
(7, 1, 'java', 'java_compile_69d0230cb00a18.84451097', 'afd9ae201949082c1d97fc4d0b8f122688453024a69af1c2d1d1a9f025f018f6', 'public class Main {\n    public static void main(String[] args) {\n        int x = 10;\n        int y = 20;\n        int result = x + y;\n        \n        System.out.println(\"Result: \" + result);\n        \n        for (int i = 0; i < 5; i++) {\n            System.out.println(\"Iteration \" + i);\n        }\n        \n        if (result > 25) {\n            System.out.println(\"Result is greater than 25\");\n        } else {\n            System.out.println(\"Result is 25 or less\");\n        }\n    }\n}', 1, 0, 1762, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:29:00'),
(8, 1, 'java', 'java_compile_69d0230e1683d0.93065077', 'afd9ae201949082c1d97fc4d0b8f122688453024a69af1c2d1d1a9f025f018f6', 'public class Main {\n    public static void main(String[] args) {\n        int x = 10;\n        int y = 20;\n        int result = x + y;\n        \n        System.out.println(\"Result: \" + result);\n        \n        for (int i = 0; i < 5; i++) {\n            System.out.println(\"Iteration \" + i);\n        }\n        \n        if (result > 25) {\n            System.out.println(\"Result is greater than 25\");\n        } else {\n            System.out.println(\"Result is 25 or less\");\n        }\n    }\n}', 1, 0, 1475, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:29:02'),
(9, 1, 'java', 'java_compile_69d023157c78f4.60236669', 'ecedb8a87352613b41ad6f2f02e158091919af6043faa97165ba2c81068018a1', 'public class Main {\n    public static void main(String[] args) {\n        int x = 10;\n        int y = 20;\n        int result = x + y;\n        System.out.println(\"Result: \" + result);\n    }\n}', 1, 0, 1564, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:29:09'),
(10, 1, 'java', 'java_compile_69d023853ede99.51079367', 'ecedb8a87352613b41ad6f2f02e158091919af6043faa97165ba2c81068018a1', 'public class Main {\n    public static void main(String[] args) {\n        int x = 10;\n        int y = 20;\n        int result = x + y;\n        System.out.println(\"Result: \" + result);\n    }\n}', 1, 0, 1392, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:31:01'),
(11, NULL, 'c', 'compile_69d083f3e9d253.17188670', '4fe97b2aa0907de38c5939dddfe44d27ab6c5db3cbdc510c57cbcfb34ff41f41', '#include <stdio.h>\n\nint main() {\n    int x = 10;\n    int y = 20;\n    int result = x + y;\n    \n    printf(\"Result: %d\\n\", result);\n    \n    for (int i = 0; i < 5; i++) {\n        printf(\"Iteration %d\\n\", i);\n    }\n    \n    return 0;\n}', 0, 4, 1690, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 03:22:28'),
(12, NULL, 'c', 'compile_69d08413ce7ff2.47074433', 'ae4cfb934f5587f20dc2502e277952d9a356524e492ef1790f14defc528e2feb', '#include <stdio.h>\n\nint main() {\n    int score = 85;\n    \n    if (score >= 90) {\n        printf(\"Grade: A\\n\");\n    } else if (score >= 80) {\n        printf(\"Grade: B\\n\");\n    } else if (score >= 70) {\n        printf(\"Grade: C\\n\");\n    } else {\n        printf(\"Grade: F\\n\");\n    }\n    \n    return 0;\n}', 1, 0, 1437, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 03:22:59'),
(13, NULL, 'c', 'compile_69d084142e2d12.50522087', 'ae4cfb934f5587f20dc2502e277952d9a356524e492ef1790f14defc528e2feb', '#include <stdio.h>\n\nint main() {\n    int score = 85;\n    \n    if (score >= 90) {\n        printf(\"Grade: A\\n\");\n    } else if (score >= 80) {\n        printf(\"Grade: B\\n\");\n    } else if (score >= 70) {\n        printf(\"Grade: C\\n\");\n    } else {\n        printf(\"Grade: F\\n\");\n    }\n    \n    return 0;\n}', 1, 0, 1626, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 03:23:00'),
(14, NULL, 'swift', 'swift_compile_69d0884e4b6643.65978564', '4c5dd8f40de84ef8b9c6f5cf71d2f27376b90cfe15307e7b1074a0ba1d8f8096', 'import Foundation\n\nfunc main() {\n    let x = 10\n    let y = 20\n    let result = x + y\n    \n    print(\"Result: \\(result)\")\n    \n    for i in 0..<5 {\n        print(\"Iteration \\(i)\")\n    }\n    \n    if result > 25 {\n        print(\"Result is greater than 25\")\n    } else {\n        print(\"Result is 25 or less\")\n    }\n}\n\n// Execute main function\nmain()', 0, 1, 1769, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 03:41:02'),
(15, NULL, 'swift', 'swift_compile_69d0885a457142.98073037', '5990174be8e346154e48d367839d2c86fb43bd13b757d7303eabe6fe817b005b', 'import Foundation\n\nfunc main() {\n    let score = 85\n    \n    if score >= 90 {\n        print(\"Grade: A\")\n    } else if score >= 80 {\n        print(\"Grade: B\")\n    } else if score >= 70 {\n        print(\"Grade: C\")\n    } else {\n        print(\"Grade: F\")\n    }\n}\n\nmain()', 1, 0, 1161, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 03:41:14');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_admin` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `created_at`, `is_admin`) VALUES
(1, 'test', 'test@gmail.com', '$2y$10$L9Vmbt084haSgdCS9XnRle7gXTc7zkIlj8R4V41DLpuH/Bp2wiucm', '2026-04-03 17:11:10', 0),
(2, 'admin', 'admin@compilerhub.dev', '$2y$10$L9Vmbt084haSgdCS9XnRle7gXTc7zkIlj8R4V41DLpuH/Bp2wiucm', '2026-04-04 03:47:58', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_activity_logs`
--

CREATE TABLE `user_activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_activity_logs`
--

INSERT INTO `user_activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(2, 1, 'register', 'User registered', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:11:25'),
(3, 1, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:12:11'),
(4, 1, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:12:16'),
(5, 1, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:12:22'),
(6, 1, 'compile', 'Compiled Brainfuck code, session: brainfuck_compile_69cffb609da2f1.58612342, errors: 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:39:44'),
(7, 1, 'compile', 'Compiled Brainfuck code, session: brainfuck_compile_69d002c803ebb6.44471221, errors: 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 18:11:21'),
(8, 1, 'compile', 'Compiled Brainfuck code, session: brainfuck_compile_69d0034d2fb440.34085202, errors: 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 18:13:33'),
(9, 1, 'download', 'Downloaded ast from session: brainfuck_compile_69d0034d2fb440.34085202', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 18:13:36'),
(10, 1, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 19:57:06'),
(11, 1, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 19:57:26'),
(12, 1, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:21:08'),
(13, 1, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:28:08'),
(14, 1, 'compile', 'Compiled Java code, session: java_compile_69d022f7e969c2.29359670, errors: 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:28:40'),
(15, 1, 'compile', 'Compiled Java code, session: java_compile_69d0230cb00a18.84451097, errors: 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:29:01'),
(16, 1, 'compile', 'Compiled Java code, session: java_compile_69d0230e1683d0.93065077, errors: 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:29:03'),
(17, 1, 'compile', 'Compiled Java code, session: java_compile_69d023157c78f4.60236669, errors: 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:29:10'),
(18, 1, 'download', 'Downloaded bytecode from session: java_compile_69d023157c78f4.60236669', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:29:16'),
(19, 1, 'compile', 'Compiled Java code, session: java_compile_69d023853ede99.51079367, errors: 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:31:01');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `compilation_logs`
--
ALTER TABLE `compilation_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_lang` (`user_id`,`language`),
  ADD KEY `idx_session` (`session_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_action` (`user_id`,`action`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `compilation_logs`
--
ALTER TABLE `compilation_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `compilation_logs`
--
ALTER TABLE `compilation_logs`
  ADD CONSTRAINT `compilation_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  ADD CONSTRAINT `user_activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
