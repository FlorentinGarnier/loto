<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251202Init extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema for loto quine app (Event, Game, Player, Card, Draw, Winner)';
    }

    public function up(Schema $schema): void
    {
        // event
        $event = $schema->createTable('event');
        $event->addColumn('id', 'integer', ['autoincrement' => true]);
        $event->addColumn('name', 'string', ['length' => 255]);
        $event->addColumn('date', 'datetime_immutable');
        $event->setPrimaryKey(['id']);

        // player
        $player = $schema->createTable('player');
        $player->addColumn('id', 'integer', ['autoincrement' => true]);
        $player->addColumn('name', 'string', ['length' => 150]);
        $player->addColumn('email', 'string', ['length' => 150, 'notnull' => false]);
        $player->addColumn('phone', 'string', ['length' => 50, 'notnull' => false]);
        $player->addColumn('notes', 'text', ['notnull' => false]);
        $player->setPrimaryKey(['id']);

        // card
        $card = $schema->createTable('card');
        $card->addColumn('id', 'integer', ['autoincrement' => true]);
        $card->addColumn('event_id', 'integer');
        $card->addColumn('reference', 'string', ['length' => 50]);
        $card->addColumn('grid', 'json');
        $card->addColumn('player_id', 'integer', ['notnull' => false]);
        $card->setPrimaryKey(['id']);
        $card->addForeignKeyConstraint('event', ['event_id'], ['id'], ['onDelete' => 'CASCADE']);
        $card->addForeignKeyConstraint('player', ['player_id'], ['id'], ['onDelete' => 'SET NULL']);
        $card->addUniqueIndex(['event_id', 'reference'], 'uniq_event_reference');

        // game
        $game = $schema->createTable('game');
        $game->addColumn('id', 'integer', ['autoincrement' => true]);
        $game->addColumn('event_id', 'integer');
        $game->addColumn('position', 'integer');
        $game->addColumn('rule', 'string', ['length' => 20]);
        $game->addColumn('prize', 'string', ['length' => 255]);
        $game->addColumn('status', 'string', ['length' => 20]);
        $game->setPrimaryKey(['id']);
        $game->addForeignKeyConstraint('event', ['event_id'], ['id'], ['onDelete' => 'CASCADE']);
        $game->addIndex(['event_id', 'position']);

        // draw
        $draw = $schema->createTable('draw');
        $draw->addColumn('id', 'integer', ['autoincrement' => true]);
        $draw->addColumn('game_id', 'integer');
        $draw->addColumn('number', 'integer');
        $draw->addColumn('order_index', 'integer');
        $draw->addColumn('created_at', 'datetime_immutable');
        $draw->setPrimaryKey(['id']);
        $draw->addForeignKeyConstraint('game', ['game_id'], ['id'], ['onDelete' => 'CASCADE']);
        $draw->addUniqueIndex(['game_id', 'number'], 'uniq_game_number');
        $draw->addIndex(['game_id', 'order_index']);

        // winner
        $winner = $schema->createTable('winner');
        $winner->addColumn('id', 'integer', ['autoincrement' => true]);
        $winner->addColumn('game_id', 'integer');
        $winner->addColumn('card_id', 'integer', ['notnull' => false]);
        $winner->addColumn('source', 'string', ['length' => 20]);
        $winner->addColumn('reference', 'string', ['length' => 255, 'notnull' => false]);
        $winner->addColumn('created_at', 'datetime_immutable');
        $winner->setPrimaryKey(['id']);
        $winner->addForeignKeyConstraint('game', ['game_id'], ['id'], ['onDelete' => 'CASCADE']);
        $winner->addForeignKeyConstraint('card', ['card_id'], ['id'], ['onDelete' => 'SET NULL']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('winner');
        $schema->dropTable('draw');
        $schema->dropTable('game');
        $schema->dropTable('card');
        $schema->dropTable('player');
        $schema->dropTable('event');
    }
}
