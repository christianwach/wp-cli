<?php

use \WP_CLI\Dispatcher,
	\WP_CLI\Utils;

/**
 * Get information about WP-CLI itself.
 *
 * @when before_wp_load
 */
class CLI_Command extends WP_CLI_Command {

	private function command_to_array( $command ) {
		$dump = array(
			'name' => $command->get_name(),
			'description' => $command->get_shortdesc(),
			'longdesc' => $command->get_longdesc(),
		);

		foreach ( $command->get_subcommands() as $subcommand ) {
			$dump['subcommands'][] = self::command_to_array( $subcommand );
		}

		if ( empty( $dump['subcommands'] ) ) {
			$dump['synopsis'] = (string) $command->get_synopsis();
		}

		return $dump;
	}

	/**
	 * Print WP-CLI version.
	 */
	function version() {
		WP_CLI::line( 'WP-CLI ' . WP_CLI_VERSION );
	}

	/**
	 * Print various data about the CLI environment.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Accepted values: json
	 */
	function info( $_, $assoc_args ) {
		$php_bin = defined( 'PHP_BINARY' ) ? PHP_BINARY : getenv( 'WP_CLI_PHP_USED' );

		$runner = WP_CLI::get_runner();

		if ( isset( $assoc_args['format'] ) && 'json' === $assoc_args['format'] ) {
			$info = array(
				'php_binary_path' => $php_bin,
				'global_config_path' => $runner->global_config_path,
				'project_config_path' => $runner->project_config_path,
				'wp_cli_dir_path' => WP_CLI_ROOT,
				'wp_cli_version' => WP_CLI_VERSION,
			);

			WP_CLI::line( json_encode( $info ) );
		} else {
			WP_CLI::line( "PHP binary:\t" . $php_bin );
			WP_CLI::line( "PHP version:\t" . PHP_VERSION );
			WP_CLI::line( "php.ini used:\t" . get_cfg_var( 'cfg_file_path' ) );
			WP_CLI::line( "WP-CLI root dir:\t" . WP_CLI_ROOT );
			WP_CLI::line( "WP-CLI global config:\t" . $runner->global_config_path );
			WP_CLI::line( "WP-CLI project config:\t" . $runner->project_config_path );
			WP_CLI::line( "WP-CLI version:\t" . WP_CLI_VERSION );
		}
	}

	/**
	 * Dump the list of global parameters, as JSON.
	 *
	 * @subcommand param-dump
	 */
	function param_dump() {
		echo json_encode( \WP_CLI::get_configurator()->get_spec() );
	}

	/**
	 * Dump the list of installed commands, as JSON.
	 *
	 * @subcommand cmd-dump
	 */
	function cmd_dump() {
		echo json_encode( self::command_to_array( WP_CLI::get_root_command() ) );
	}

	/**
	 * Generate tab completion strings.
	 *
	 * ## OPTIONS
	 *
	 * --line=<line>
	 * : The current command line to be executed
	 *
	 * --point=<point>
	 * : The index to the current cursor position relative to the beginning of the command
	 */
	function completions( $_, $assoc_args ) {
		$line = substr( $assoc_args['line'], 0, $assoc_args['point'] );

		$r = self::parse_line( $line );
		if ( !is_array( $r ) ) {
			return;
		}

		list( $command, $args, $assoc_args ) = $r;

		$spec = \WP_CLI\SynopsisParser::parse( $command->get_synopsis() );

		foreach ( $spec as $arg ) {
			if ( $arg['type'] == 'positional' && $arg['name'] == 'file' ) {
				WP_CLI::line( '<file>' );
				return;
			}
		}

		$subcommands = $command->get_subcommands();

		WP_CLI::line( implode( ' ', array_keys( $subcommands ) ) );
	}

	private static function parse_line( $line ) {
		$ends_with_space = ( ' ' === substr( $line, -1 ) );

		// TODO: properly parse single and double quotes
		$words = explode( ' ', $line );

		array_shift( $words );  // first word is always `wp`
		array_pop( $words );  // last word is either a space or an incomplete subcommand

		list( $positional_args, $assoc_args ) = \WP_CLI\Configurator::extract_assoc( $words );

		$r = \WP_CLI::get_runner()->find_command_to_run( $positional_args );
		if ( !is_array( $r ) ) {
			return $r;
		}

		list( $command, $args ) = $r;

		return array( $command, $args, $assoc_args );
	}
}

WP_CLI::add_command( 'cli', 'CLI_Command' );

