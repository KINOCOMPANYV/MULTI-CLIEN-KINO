PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
// error_log("✅ [CONFIG] Conexión DB establecida correctamente a {$DB_NAME}");
} catch (PDOException $e) {
error_log("❌ [CONFIG] Error de conexión DB: " . $e->getMessage());
die("❌ Error de conexión a la base de datos.");
}
?>