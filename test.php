PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
echo "✅ Conexión exitosa con la base de datos: <b>{$DB_NAME}</b>";
} catch (PDOException $e) {
echo "❌ Error de conexión: " . $e->getMessage();
}
?>