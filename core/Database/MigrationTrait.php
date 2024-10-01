<?php

namespace Core\Database;

use Core\Schema;
use DirectoryIterator;
use PDO;
use PDOException;

trait MigrationTrait
{
	public function handleApplyMigrations(PDO $pdo)
	{
		echo "Migration start:" . PHP_EOL;
		$this->createMigrationTable($pdo);
		$migrationDirectory = __DIR__ . '/../../database/migrations';
		$oldMigrations = $this->getMigrations($pdo);
		$newMigrations = [];
		foreach (new DirectoryIterator($migrationDirectory) as $file) {
			if ($file->isDot()) continue;

			$fileName = $file->getFilename();
			if ($file->isFile() && !in_array($fileName, $oldMigrations)) {
				$filePath = $file->getPathname();
				$migrateClass = include $filePath;
				$migrateClass->up();
				echo $file->getFilename() . PHP_EOL;
				$newMigrations[] = $fileName;
			}
		}

		$this->insertMigration($pdo, $newMigrations);
		echo 'DONE!' . PHP_EOL;
	}

	public function handleRollbackMigrations(PDO $pdo)
	{
		echo "Migration rollback start:" . PHP_EOL;
		$migrationDirectory = __DIR__ . '/../../database/migrations';
		$oldMigrations = $this->getMigrations($pdo);
		if (!count($oldMigrations)) {
			echo 'DONE!' . PHP_EOL;
			return;
		}

		$migrate = $oldMigrations[0];
		$migrateClass = include $migrationDirectory . '/' . $migrate;
		$migrateClass->down();
		$this->removeLastMigration($pdo);
		echo $migrate . PHP_EOL;
		echo 'DONE!' . PHP_EOL;
	}

	private function createMigrationTable(PDO $pdo)
	{
		$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
			id INT AUTO_INCREMENT PRIMARY KEY,
			migration VARCHAR(255),
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		)");
	}

	private function getMigrations(PDO $pdo)
	{
		try {
			$sql = "SELECT migration FROM migrations ORDER BY id DESC";
			$stmt = $pdo->prepare($sql);
			$stmt->execute();
			$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$results = array_map(fn($item)  => $item['migration'], $results);
			
			return $results;
		} catch (PDOException $e) {
			echo $e->getMessage() . PHP_EOL;
		}
	}

	private function insertMigration(PDO $pdo, array $values)
	{
		try {
			if (!count($values)) return;
			$pdo->beginTransaction();
			$pdo->exec("INSERT INTO migrations (migration) VALUES ('" . implode("'),('", $values) . "');");
			$pdo->exec(implode(' ', Schema::$sql));
			$pdo->commit();
		} catch (PDOException $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			echo $e->getMessage() . PHP_EOL;
		}
	}

	private function removeLastMigration(PDO $pdo)
	{
		try {
			$pdo->beginTransaction();
			$sql = "DELETE FROM `migrations` WHERE id = (SELECT id FROM (SELECT MAX(id) AS id FROM `migrations`) AS `temp_table`);";
			$sql .= implode(' ', Schema::$sql);
			$pdo->exec($sql);
			$pdo->commit();
		} catch (PDOException $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			echo $e->getMessage() . PHP_EOL;
		}
	}
}