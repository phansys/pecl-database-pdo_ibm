--TEST--
pdo_ibm: Select LOBs, including null and 0-length
--SKIPIF--
<?php require_once('skipif.inc'); ?>
--FILE--
<?php
	require_once('fvt.inc');

	class Test extends FVTTest
	{
		public function runTest()
		{
			$this->connect();

			try {
				/* Drop the test table, in case it exists */
				$drop = 'DROP TABLE animals';
				$result = $this->db->exec( $drop );
			} catch( Exception $e ){}

			/* Create the test table */
			$create = 'CREATE TABLE animals (id INTEGER, my_clob clob, my_blob blob)';
			$result = $this->db->exec( $create );

			$clobStream = tmpfile();
			fwrite($clobStream, "this is the clob resource that never ends...");

			$blobStream = tmpfile();
			fwrite($blobStream, "this is the blob resource that never ends...");

			$data = array (
				array(
					array(PDO::PARAM_INT, 1),
					array(PDO::PARAM_STR, 'this is the clob that never ends...'),
					array(PDO::PARAM_STR, 'this is the blob that never ends...'),
				),
				array(
					array(PDO::PARAM_INT, 2),
					array(PDO::PARAM_STR, null),
					array(PDO::PARAM_STR, null)
				),
				array(
					array(PDO::PARAM_INT, 3),
					array(PDO::PARAM_STR, ''),
					array(PDO::PARAM_STR, '')
				),
				array(
					array(PDO::PARAM_INT, 4),
					array(PDO::PARAM_LOB, $clobStream),
					array(PDO::PARAM_LOB, $blobStream)
				)
			);

			$stmt = $this->db->prepare('insert into animals (id, my_clob, my_blob) values (?, ?, ?);');
			$stmt->bindParam(1, $id);
			$stmt->bindParam(2, $_FILES['file']['type']);
			$stmt->bindParam(3, $fp, PDO::PARAM_LOB);

			print "inserting\n";
			foreach ($data as $row) {
				foreach ($row as $position => $value) {
					$stmt->bindValue($position + 1, $value[1], $value[0]);
				}
				$stmt->execute();
			}

			fclose($clobStream);
			fclose($blobStream);

			print "succesful\n";
			/*
			print "running query\n";

			$stmt = $this->db->prepare( 'select id,my_clob,my_blob from animals' );

			$rs = $stmt->execute();

			$count = 0;
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				var_dump( $row['ID'] );

				// this is a temporary workaround
				// until zero-length/lob stream
				// issue is fixed
				if ($count < 2) {
				   var_dump( $row['MY_CLOB'] );
				   var_dump( $row['MY_BLOB'] );
				}
				// suppressed deprecation message for NULL on 8.1
				var_dump(@strpos($row['MY_CLOB'], 'lob'));
				$count++;
			}

			print "succesful\n";
			*/
			print "running query\n";

			$stmt = $this->db->prepare('SELECT id, my_clob, my_blob FROM animals;');

			$rs = $stmt->execute();

			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $i => $row) {
				var_dump($row['ID']);

				if (is_resource($row['MY_CLOB'])) {
					$clob = stream_get_contents($row['MY_CLOB']);
				} else {
					$clob = $row['MY_CLOB'];
				}

				if (is_resource($row['MY_BLOB'])) {
					$blob = stream_get_contents($row['MY_BLOB']);
				} else {
					$blob = $row['MY_BLOB'];
				}

				var_dump($clob);
				var_dump($blob);
			}

			print "done\n";
		}
	}

	$testcase = new Test();
	$testcase->runTest();
?>

--EXPECTF--
inserting
succesful
running query
string(1) "1"
string(35) "this is the clob that never ends..."
string(35) "this is the blob that never ends..."
int(13)
string(1) "2"
NULL
NULL
bool(false)
string(1) "3"
bool(false)
done

