<?php
/**
 * Migration Runner page for When Last Login.
 *
 * Allows manual triggering and monitoring of the CPT → table migration.
 *
 * @package When_Last_Login
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$migration_status = get_option( 'wll_migration_status', array() );
$db_version        = get_option( 'wll_db_version', '1.0.0' );

$total     = isset( $migration_status['total'] ) ? (int) $migration_status['total'] : 0;
$migrated  = isset( $migration_status['migrated'] ) ? (int) $migration_status['migrated'] : 0;
$status    = isset( $migration_status['status'] ) ? $migration_status['status'] : 'unknown';
$started   = isset( $migration_status['started'] ) ? $migration_status['started'] : '';
$completed = isset( $migration_status['completed'] ) ? $migration_status['completed'] : '';

// Get live counts.
if ( class_exists( 'WLL_DB' ) ) {
	$remaining = (int) WLL_DB::count_posts_to_migrate();
	if ( $remaining > 0 ) {
		$total = $migrated + $remaining;
	}
}

$percentage = $total > 0 ? round( ( $migrated / $total ) * 100 ) : 0;
$is_complete = ( $status === 'complete' || $remaining === 0 );
$can_run = ! $is_complete && $remaining > 0;
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Migration Runner', 'when-last-login' ); ?></h1>
	<p><?php esc_html_e( 'Manually migrate login records from the custom post type to the database tables.', 'when-last-login' ); ?></p>

	<h2><?php esc_html_e( 'Status', 'when-last-login' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Database Version', 'when-last-login' ); ?></th>
			<td><code><?php echo esc_html( $db_version ); ?></code></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Migration Status', 'when-last-login' ); ?></th>
			<td>
				<?php
				$badge_class = 'complete' === $status ? 'status-complete' : ( 'in_progress' === $status ? 'status-running' : 'status-pending' );
				?>
				<span class="wll-migration-badge wll-<?php echo esc_attr( $badge_class ); ?>">
					<?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?>
				</span>
			</td>
		</tr>
		<?php if ( $started ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Started', 'when-last-login' ); ?></th>
			<td><?php echo esc_html( $started ); ?></td>
		</tr>
		<?php endif; ?>
		<?php if ( $completed ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Completed', 'when-last-login' ); ?></th>
			<td><?php echo esc_html( $completed ); ?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Records Migrated', 'when-last-login' ); ?></th>
			<td><?php echo esc_html( number_format( $migrated ) . ' / ' . number_format( $total ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Remaining', 'when-last-login' ); ?></th>
			<td id="wll-remaining"><?php echo esc_html( number_format( $remaining ) ); ?></td>
		</tr>
	</table>

	<?php if ( $total > 0 ) : ?>
	<h3><?php esc_html_e( 'Progress', 'when-last-login' ); ?></h3>
	<div style="max-width:600px;margin-bottom:20px;">
		<div style="background:#e0e0e0;border-radius:4px;overflow:hidden;height:30px;">
			<div id="wll-progress-bar" style="background:#2271b1;height:100%;width:<?php echo esc_attr( $percentage ); ?>%;transition:width 0.5s;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;">
				<?php echo esc_html( $percentage ); ?>%
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( $can_run ) : ?>
	<p>
		<button id="wll-run-migration" class="button button-primary"><?php esc_html_e( 'Run Migration', 'when-last-login' ); ?></button>
		<button id="wll-run-batch" class="button"><?php esc_html_e( 'Run Single Batch', 'when-last-login' ); ?></button>
	</p>
	<?php else : ?>
	<p>
		<button class="button" disabled><?php esc_html_e( 'Migration Complete', 'when-last-login' ); ?></button>
	</p>
	<?php endif; ?>

	<p>
		<button id="wll-reset-migration" class="button button-link-delete" style="margin-left:10px;">
			<?php esc_html_e( 'Reset Migration', 'when-last-login' ); ?>
		</button>
	</p>

	<div id="wll-migration-log" style="max-width:800px;margin-top:20px;background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:4px;font-family:monospace;font-size:13px;max-height:400px;overflow-y:auto;display:none;">
		<div id="wll-log-content"></div>
	</div>
</div>

<script>
(function($) {
	var running = false;
	var logEl = $('#wll-log-content');
	var logBox = $('#wll-migration-log');

	function log(msg) {
		var ts = new Date().toLocaleTimeString();
		logEl.append('<div>[' + ts + '] ' + msg + '</div>');
		logBox.scrollTop(logBox[0].scrollHeight);
	}

	function updateStatus() {
		$.post(ajaxurl, { action: 'wll_migration_runner_status' }, function(resp) {
			if (!resp.success) return;
			var d = resp.data;
			$('#wll-remaining').text(d.remaining.toLocaleString());
			if (d.total > 0) {
				var pct = Math.round((d.migrated / d.total) * 100);
				$('#wll-progress-bar').css('width', pct + '%').text(pct + '%');
			}
		}, 'json');
	}

	var prevMigrated = 0;
	function runBatch() {
		return $.post(ajaxurl, {
			action: 'wll_migration_run_batch',
			nonce: wllMigration.nonce,
			prev_migrated: prevMigrated
		}, 'json').then(function(resp) {
			if (!resp.success) {
				log('<span style="color:#f44336;">Error: ' + (resp.data || 'Unknown') + '</span>');
				running = false;
				$('#wll-run-migration').prop('disabled', false).text('Run Migration');
				return false;
			}
			var d = resp.data;
			prevMigrated = d.migrated;
			log('Batch complete: ' + d.migrated_in_batch + ' records migrated (' + d.migrated + '/' + d.total + ')');
			updateStatus();
			if (d.complete) {
				log('<span style="color:#4caf50;">Migration complete!</span>');
				running = false;
				$('#wll-run-migration').prop('disabled', true).text('Migration Complete');
				$('#wll-run-batch').prop('disabled', true);
				return false;
			}
			return true;
		});
	}

	$('#wll-run-migration').on('click', function() {
		if (running) return;
		running = true;
		logBox.show();
		log('<span style="color:#4caf50;">Starting migration...</span>');
		$(this).prop('disabled', true).text('Running...');

		function loop() {
			runBatch().then(function(continueLoop) {
				if (continueLoop && running) {
					setTimeout(loop, 500);
				} else if (!running) {
					$('#wll-run-migration').prop('disabled', false).text('Run Migration');
				}
			});
		}
		loop();
	});

	$('#wll-run-batch').on('click', function() {
		logBox.show();
		log('Running single batch...');
		$(this).prop('disabled', true);
		runBatch().then(function() {
			$('#wll-run-batch').prop('disabled', false);
		});
	});

	$('#wll-reset-migration').on('click', function() {
		if (!confirm('Reset migration? This will clear the migration status and allow re-running.')) return;
		logBox.show();
		log('<span style="color:#ff9800;">Resetting migration...</span>');
		$.post(ajaxurl, {
			action: 'wll_migration_reset',
			nonce: wllMigration.nonce
		}, function(resp) {
			if (resp.success) {
				log('<span style="color:#4caf50;">Reset complete. Reloading...</span>');
				setTimeout(function() { location.reload(); }, 1000);
			} else {
				log('<span style="color:#f44336;">Reset failed: ' + (resp.data || 'Unknown') + '</span>');
			}
		}, 'json');
	});
})(jQuery);
</script>
