-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 30, 2025 at 04:38 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pfe`
--

-- --------------------------------------------------------

--
-- Table structure for table `attestations`
--

CREATE TABLE `attestations` (
  `attestationID` int(11) NOT NULL,
  `stageID` int(11) NOT NULL,
  `dateGeneration` datetime DEFAULT current_timestamp(),
  `qrCodeData` text DEFAULT NULL,
  `pdf_file_path` varchar(255) DEFAULT NULL,
  `verificationToken` varchar(64) DEFAULT NULL,
  `pdfType` varchar(50) NOT NULL DEFAULT 'attestation'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attestations`
--

INSERT INTO `attestations` (`attestationID`, `stageID`, `dateGeneration`, `qrCodeData`, `pdf_file_path`, `verificationToken`, `pdfType`) VALUES
(12, 9, '2025-07-29 00:00:00', NULL, 'http://localhost/Backend/Files/attestation_9_6888af8a158e7.pdf', NULL, 'attestation'),
(13, 9, '2025-07-29 00:00:00', NULL, 'http://localhost/Backend/Files/paie_9_6888ae68113af.pdf', NULL, 'paie');

-- --------------------------------------------------------

--
-- Table structure for table `attestationsstage`
--

CREATE TABLE `attestationsstage` (
  `attestationID` int(11) NOT NULL,
  `stageID` int(11) NOT NULL,
  `dateGeneration` date DEFAULT curdate(),
  `path` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_url` varchar(255) NOT NULL,
  `dateGeneration` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `stage_id`, `document_type`, `document_url`, `dateGeneration`, `created_at`, `updated_at`) VALUES
(2, 9, 'paie', 'http://localhost/Backend/Files/paie_9_6889ddcda23f6.pdf', '2025-07-30', '2025-07-29 11:25:30', '2025-07-30 08:54:37'),
(4, 14, 'paie', 'http://localhost/Backend/Files/paie_14_6888b4dc8f164.pdf', '2025-07-29', '2025-07-29 11:33:34', '2025-07-29 11:47:40'),
(5, 9, 'attestation', 'http://localhost/Backend/Files/attestation_9_6889ec5508bfd.pdf', '2025-07-30', '2025-07-29 15:37:32', '2025-07-30 09:56:37'),
(6, 14, 'attestation', 'http://localhost/Backend/Files/attestation_14_6889c77fcf515.pdf', '2025-07-30', '2025-07-30 07:19:27', '2025-07-30 07:19:27');

-- --------------------------------------------------------

--
-- Table structure for table `etudiants`
--

CREATE TABLE `etudiants` (
  `etudiantID` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `cin` varchar(50) NOT NULL,
  `niveauEtude` varchar(50) DEFAULT NULL,
  `nomFaculte` varchar(100) NOT NULL,
  `cycle` varchar(50) NOT NULL,
  `specialite` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `etudiants`
--

INSERT INTO `etudiants` (`etudiantID`, `username`, `lastname`, `email`, `cin`, `niveauEtude`, `nomFaculte`, `cycle`, `specialite`) VALUES
(4, 'aymen', 'ali', 'mohamed.amin.khaskhoussi@gmail.com', '11145365', 'Anneé 1', 'FST', 'Master', 'Info'),
(5, 'hedi', 'ali', 'knightw699@gmail.com', '1111111', 'Anneé 1', 'FST', 'ingénierie', 'Info'),
(6, 'test', 'test', 'sss@sss.sss', '1', 'Anneé 1', 'FST', 'Licence', 'Info'),
(7, 'sdsd', 'ssd', 'sdsd@sdsd.sdsd', '111', 'Anneé 1', 'FST', 'Licence', 'Electronique'),
(8, 'assas', 'ssaaas', 'knighs@sss.sss', '12414', 'Anneé 1', 'FST', 'Licence', 'Info'),
(9, 'kjjjk', 'kjjkl', 'knqs@sss.ss', '45241', 'Anneé 1', 'FST', 'Master', 'Electronique');

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `evaluationID` int(11) NOT NULL,
  `stageID` int(11) NOT NULL,
  `dateEvaluation` date NOT NULL,
  `note` decimal(4,2) DEFAULT NULL,
  `commentaires` text DEFAULT NULL,
  `encadrantID` int(11) NOT NULL,
  `chefCentreValidationID` int(11) DEFAULT NULL,
  `dateValidationChef` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluations`
--

INSERT INTO `evaluations` (`evaluationID`, `stageID`, `dateEvaluation`, `note`, `commentaires`, `encadrantID`, `chefCentreValidationID`, `dateValidationChef`) VALUES
(3, 9, '2025-07-29', 9.00, '', 5, 3, '2025-07-29'),
(4, 14, '2025-07-30', 8.00, 'bravoo', 5, 3, '2025-07-29'),
(5, 21, '2025-07-30', 9.00, 'kljklj', 5, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `paiements`
--

CREATE TABLE `paiements` (
  `paiementID` int(11) NOT NULL,
  `stageID` int(11) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `datePaiement` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scanned_subjects`
--

CREATE TABLE `scanned_subjects` (
  `SubjectID` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `pdfFileUrl` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stagenotes`
--

CREATE TABLE `stagenotes` (
  `noteID` int(11) NOT NULL,
  `stageID` int(11) NOT NULL,
  `encadrantID` int(11) NOT NULL,
  `dateNote` timestamp NOT NULL DEFAULT current_timestamp(),
  `contenuNote` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stagenotes`
--

INSERT INTO `stagenotes` (`noteID`, `stageID`, `encadrantID`, `dateNote`, `contenuNote`) VALUES
(5, 9, 5, '2025-07-29 08:42:58', 'nl,njl'),
(6, 14, 5, '2025-07-29 11:30:59', 'baddel background'),
(7, 14, 5, '2025-07-29 11:31:10', 'yaatik sa7a'),
(8, 26, 5, '2025-07-30 08:51:04', 'changer email d\'affectation');

-- --------------------------------------------------------

--
-- Table structure for table `stages`
--

CREATE TABLE `stages` (
  `stageID` int(11) NOT NULL,
  `etudiantID` int(11) NOT NULL,
  `sujetID` int(11) DEFAULT NULL,
  `typeStage` varchar(50) NOT NULL,
  `dateDebut` date NOT NULL,
  `dateFin` date NOT NULL,
  `statut` enum('Proposé','Validé','Refusé','En cours','Terminé','Annulé','accepté') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_nopad_ci NOT NULL DEFAULT 'Proposé',
  `estRemunere` tinyint(1) NOT NULL DEFAULT 0,
  `montantRemuneration` decimal(10,2) DEFAULT NULL,
  `encadrantProID` int(11) DEFAULT NULL,
  `encadrantAcademiqueID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stages`
--

INSERT INTO `stages` (`stageID`, `etudiantID`, `sujetID`, `typeStage`, `dateDebut`, `dateFin`, `statut`, `estRemunere`, `montantRemuneration`, `encadrantProID`, `encadrantAcademiqueID`) VALUES
(9, 4, 2, 'PFA', '2025-07-29', '2025-07-29', 'Validé', 1, 150.00, 5, NULL),
(10, 4, NULL, 'Pfa', '2024-09-01', '2025-02-28', 'Refusé', 1, 90.50, 5, NULL),
(11, 4, NULL, 'Pfa', '2024-09-01', '2025-02-28', 'accepté', 1, 90.50, 5, NULL),
(12, 4, NULL, 'Pfa', '2024-09-01', '2025-02-28', 'accepté', 1, 90.50, 5, NULL),
(13, 4, NULL, 'Pfa', '2024-09-01', '2025-02-28', 'accepté', 1, 90.50, 5, NULL),
(14, 5, 3, 'PFA', '2025-07-29', '2025-07-29', 'Terminé', 1, 40.00, 5, NULL),
(17, 4, NULL, 'PFA', '2025-07-29', '2025-07-29', 'Proposé', 0, NULL, 5, NULL),
(18, 4, NULL, 'PFA', '2025-07-29', '2025-07-29', 'Proposé', 0, NULL, 5, NULL),
(19, 4, 2, 'PFE', '2025-09-01', '2025-12-31', 'Refusé', 1, 500.75, 5, NULL),
(20, 4, NULL, 'PFA', '2025-07-29', '2025-07-29', 'Proposé', 0, NULL, 5, NULL),
(21, 5, NULL, 'PFA', '2025-07-29', '2025-07-29', 'Terminé', 0, NULL, 5, NULL),
(22, 5, NULL, 'PFA', '2025-07-29', '2025-07-29', 'Proposé', 0, NULL, 37, NULL),
(23, 7, NULL, 'Stage Ouvrier', '2025-07-29', '2025-07-29', 'Refusé', 0, NULL, 5, NULL),
(24, 4, NULL, 'Stage Ouvrier', '2025-07-29', '2025-07-29', 'Proposé', 0, NULL, 5, 36),
(25, 4, 2, 'PFE', '2025-09-01', '2025-12-31', 'Proposé', 1, 500.75, 5, 36),
(26, 5, 4, 'PFA', '2025-07-30', '2025-07-31', 'En cours', 1, 150.00, 5, 38);

-- --------------------------------------------------------

--
-- Table structure for table `sujetsstage`
--

CREATE TABLE `sujetsstage` (
  `sujetID` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sujetsstage`
--

INSERT INTO `sujetsstage` (`sujetID`, `titre`, `description`) VALUES
(2, 'test', 'test'),
(3, 'what', 'teyghs'),
(4, 'application web', 'ssss');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userID` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `role` enum('Gestionnaire','Encadrant','ChefCentreInformatique','EncadrantAcademique') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userID`, `username`, `password`, `email`, `lastname`, `role`) VALUES
(1, 'gestionnaire', '$2y$10$6A0xngLgNuTQIx8fHbwaiee.Z1Q5aMj5euQ.sZCA2ttwzVr1pRV9O', 'gest@steg.com', 'stegg', 'Gestionnaire'),
(3, 'test', '$2y$10$okvQHn95aNA8SFO9CKS6iu4yVf7XeIbcQ4V/UPTyyf/r9gu8l2gva', 'chef@steg.com', 'stegtest', 'ChefCentreInformatique'),
(5, 'Encadranted', '$2y$10$uMg6n.kfQCrngE8EpLBujOasFnVvS768FaptHyBc3Fx5GaYdCHgNW', 'encad@steg.com', 'steg', 'Encadrant'),
(36, 'amrni', '$2y$10$53OTOsqZRj.jjy0W6Dh3FOZVXjigKwqrX31AwxVwFWEKx50vksFlC', 'kj@ss.ss', 'jhjh', 'EncadrantAcademique'),
(37, 'hhhh', '$2y$10$qUxh.egb7xNLiTVj6le8qO2.DRJGrnZiMHtfXi2V5oFMIUx1QtNjC', 'jjj@klkl.qq', 'hhh', 'Encadrant'),
(38, 'nawel', '$2y$10$d9eMGBk81v7SZWzuWMLw/OpY1G1W7/XA6KaahKrFxtS0z.pYtR4qS', 'bjj@bbn.jb', 'mosbah', 'EncadrantAcademique');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attestations`
--
ALTER TABLE `attestations`
  ADD PRIMARY KEY (`attestationID`),
  ADD UNIQUE KEY `verificationToken` (`verificationToken`),
  ADD KEY `stageID` (`stageID`);

--
-- Indexes for table `attestationsstage`
--
ALTER TABLE `attestationsstage`
  ADD PRIMARY KEY (`attestationID`),
  ADD UNIQUE KEY `stageID` (`stageID`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_document` (`stage_id`,`document_type`);

--
-- Indexes for table `etudiants`
--
ALTER TABLE `etudiants`
  ADD PRIMARY KEY (`etudiantID`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`evaluationID`),
  ADD UNIQUE KEY `stageID` (`stageID`),
  ADD KEY `encadrantID` (`encadrantID`),
  ADD KEY `chefCentreValidationID` (`chefCentreValidationID`);

--
-- Indexes for table `paiements`
--
ALTER TABLE `paiements`
  ADD PRIMARY KEY (`paiementID`),
  ADD UNIQUE KEY `stageID` (`stageID`);

--
-- Indexes for table `scanned_subjects`
--
ALTER TABLE `scanned_subjects`
  ADD PRIMARY KEY (`SubjectID`);

--
-- Indexes for table `stagenotes`
--
ALTER TABLE `stagenotes`
  ADD PRIMARY KEY (`noteID`),
  ADD KEY `stageID` (`stageID`),
  ADD KEY `encadrantID` (`encadrantID`);

--
-- Indexes for table `stages`
--
ALTER TABLE `stages`
  ADD PRIMARY KEY (`stageID`),
  ADD KEY `etudiantID` (`etudiantID`),
  ADD KEY `sujetID` (`sujetID`),
  ADD KEY `encadrantProID` (`encadrantProID`),
  ADD KEY `chefCentreValidationID` (`encadrantAcademiqueID`);

--
-- Indexes for table `sujetsstage`
--
ALTER TABLE `sujetsstage`
  ADD PRIMARY KEY (`sujetID`),
  ADD UNIQUE KEY `titre` (`titre`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userID`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attestations`
--
ALTER TABLE `attestations`
  MODIFY `attestationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `attestationsstage`
--
ALTER TABLE `attestationsstage`
  MODIFY `attestationID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `etudiants`
--
ALTER TABLE `etudiants`
  MODIFY `etudiantID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `evaluationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `paiementID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scanned_subjects`
--
ALTER TABLE `scanned_subjects`
  MODIFY `SubjectID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stagenotes`
--
ALTER TABLE `stagenotes`
  MODIFY `noteID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `stages`
--
ALTER TABLE `stages`
  MODIFY `stageID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `sujetsstage`
--
ALTER TABLE `sujetsstage`
  MODIFY `sujetID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attestations`
--
ALTER TABLE `attestations`
  ADD CONSTRAINT `attestations_ibfk_1` FOREIGN KEY (`stageID`) REFERENCES `stages` (`stageID`);

--
-- Constraints for table `attestationsstage`
--
ALTER TABLE `attestationsstage`
  ADD CONSTRAINT `attestationsstage_ibfk_1` FOREIGN KEY (`stageID`) REFERENCES `stages` (`stageID`) ON DELETE CASCADE;

--
-- Constraints for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`stageID`) REFERENCES `stages` (`stageID`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`encadrantID`) REFERENCES `users` (`userID`),
  ADD CONSTRAINT `evaluations_ibfk_3` FOREIGN KEY (`chefCentreValidationID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `paiements`
--
ALTER TABLE `paiements`
  ADD CONSTRAINT `paiements_ibfk_1` FOREIGN KEY (`stageID`) REFERENCES `stages` (`stageID`) ON DELETE CASCADE;

--
-- Constraints for table `stagenotes`
--
ALTER TABLE `stagenotes`
  ADD CONSTRAINT `stagenotes_ibfk_1` FOREIGN KEY (`stageID`) REFERENCES `stages` (`stageID`) ON DELETE CASCADE,
  ADD CONSTRAINT `stagenotes_ibfk_2` FOREIGN KEY (`encadrantID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `stages`
--
ALTER TABLE `stages`
  ADD CONSTRAINT `stages_ibfk_1` FOREIGN KEY (`etudiantID`) REFERENCES `etudiants` (`etudiantID`),
  ADD CONSTRAINT `stages_ibfk_2` FOREIGN KEY (`sujetID`) REFERENCES `sujetsstage` (`sujetID`),
  ADD CONSTRAINT `stages_ibfk_3` FOREIGN KEY (`encadrantProID`) REFERENCES `users` (`userID`),
  ADD CONSTRAINT `stages_ibfk_4` FOREIGN KEY (`encadrantAcademiqueID`) REFERENCES `users` (`userID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
