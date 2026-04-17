-- Add interview score and notes columns to interview_bookings
ALTER TABLE `interview_bookings`
  ADD COLUMN IF NOT EXISTS `interview_score` INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `interview_notes` TEXT NULL DEFAULT NULL;
