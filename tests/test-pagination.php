<?php
/**
 * Server-side pagination, search, status/flag filters, client grouping.
 *
 * @package wpcable
 */

$tasks = new wpcable_tasks();

$all = $tasks->query_tasks(
	array(
		'page'     => 1,
		'per_page' => 25,
		'state'    => 'all',
	)
);

wpcable_assert( $all['total'] > 25, 'fixture has more than one page of imported rows' );
wpcable_assert_equals( 25, count( $all['items'] ), 'page size 25 is honored' );
wpcable_assert_equals( 1, $all['page'], 'page number echoed' );

$page2 = $tasks->query_tasks(
	array(
		'page'     => 2,
		'per_page' => 25,
		'state'    => 'all',
	)
);
$ids1 = array_map(
	function ( $row ) {
		return $row['task_id'];
	},
	$all['items']
);
$ids2 = array_map(
	function ( $row ) {
		return $row['task_id'];
	},
	$page2['items']
);
wpcable_assert( array() === array_intersect( $ids1, $ids2 ), 'page 2 does not repeat page 1 ids' );

$published = $tasks->query_tasks(
	array(
		'page'     => 1,
		'per_page' => 50,
		'state'    => 'published',
	)
);
foreach ( $published['items'] as $row ) {
	wpcable_assert_equals( 'published', $row['state'], 'published filter returns published state #' . $row['task_id'] );
	wpcable_assert( 'lost' !== $row['flag'], 'published filter excludes lost flag #' . $row['task_id'] );
}

$search = $tasks->query_tasks(
	array(
		'page'     => 1,
		'per_page' => 25,
		'search'   => 'Alpha site rebuild',
	)
);
wpcable_assert( $search['total'] >= 1, 'title search finds Alpha site rebuild' );
wpcable_assert_equals( 1, (int) $search['items'][0]['task_id'], 'search hit is task 1' );

$client_cluster = $tasks->query_tasks(
	array(
		'page'      => 1,
		'per_page'  => 25,
		'client_id' => 101,
	)
);
wpcable_assert_equals( 3, $client_cluster['total'], 'client_id 101 has three projects' );
foreach ( $client_cluster['items'] as $row ) {
	wpcable_assert_equals( 101, $row['client_id'], 'cluster stays on client 101' );
	wpcable_assert_equals( 'Fixture Client Alpha', $row['client_name'], 'client name joined from clients table' );
}

$grouped = $tasks->query_tasks(
	array(
		'page'            => 1,
		'per_page'        => 25,
		'group_by_client' => true,
	)
);
wpcable_assert( ! empty( $grouped['group_by_client'] ), 'group_by_client flag set' );
wpcable_assert( isset( $grouped['total_clients'] ), 'grouped result reports client count' );
wpcable_assert( $grouped['total_clients'] >= 2, 'at least two fixture clients grouped' );

$notes = $tasks->query_tasks(
	array(
		'page'      => 1,
		'per_page'  => 25,
		'has_notes' => true,
	)
);
wpcable_assert( $notes['total'] >= 1, 'has_notes filter finds local notes' );
wpcable_assert( $notes['items'][0]['has_notes'], 'notes row marked has_notes' );

$lost = $tasks->query_tasks(
	array(
		'page'     => 1,
		'per_page' => 25,
		'lost'     => true,
	)
);
wpcable_assert( $lost['total'] >= 1, 'lost filter finds heuristic lost row' );

$counts = $tasks->get_state_counts();
wpcable_assert( $counts['imported'] === $all['total'], 'KPI imported count matches unfiltered total' );
wpcable_assert( $counts['flag_lost'] >= 1, 'lost KPI is computed, not hardcoded' );
wpcable_assert( $counts['published'] >= 1, 'published KPI computed from DB' );

$preferred = $tasks->query_tasks(
	array(
		'page'      => 1,
		'per_page'  => 25,
		'preferred' => true,
	)
);
foreach ( $preferred['items'] as $row ) {
	wpcable_assert( $row['preferred'], 'preferred filter only preferred rows' );
}

$zero = $tasks->get_task( 999999 );
wpcable_assert( null === $zero, 'missing task is null, not a fake client' );

$clients = new wpcable_clients();
$workspace = $clients->get_client_workspace(
	array(
		'page'     => 1,
		'per_page' => 25,
		'search'   => 'Fixture Client Alpha',
	)
);
wpcable_assert_equals( 1, $workspace['total'], 'client search by name' );
wpcable_assert_equals( 101, $workspace['items'][0]['client_id'], 'search resolves client_id 101' );
