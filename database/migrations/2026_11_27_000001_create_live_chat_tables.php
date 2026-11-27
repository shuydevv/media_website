<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Собственный чат трансляций вместо чата Kinescope (ТЗ «чат трансляций — этап 1»).
 *
 * lesson_chats.version — счётчик изменений чата урока: любое изменение
 * (сообщение, реакция, удаление, ✓ на вопросе, закреп, мьют) увеличивает его и
 * проставляет номер изменённому сообщению. Браузер спрашивает «что после
 * версии N» — пустой ответ, если ничего не менялось (App\Service\LiveChat).
 *
 * lesson_live_segments — куски эфира между обрывами по сигналам Kinescope:
 * по ним чат открывается/закрывается и сообщения встают на место в записи.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lesson_id')->unique();
            $table->unsignedBigInteger('version')->default(0);
            $table->integer('shift_seconds')->default(0);
            $table->unsignedBigInteger('pinned_message_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lesson_live_segments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lesson_id')->index();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lesson_id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('author_name', 64);
            $table->string('author_badge', 32)->nullable();
            $table->text('body');
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->boolean('is_question')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->unsignedBigInteger('answered_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();

            $table->index(['lesson_id', 'version']);
            $table->index(['lesson_id', 'user_id', 'id']);
        });

        Schema::create('chat_reaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('noto_code', 32)->nullable();   // стандартная: файлы public/img/chat-reactions/noto/<code>.*
            $table->string('emoji', 16)->nullable();       // символ — подпись/запасной вариант
            $table->string('file_path')->nullable();       // своя: файл в storage/app/public
            $table->string('format', 8)->default('noto');  // noto | webp | gif | lottie | png
            $table->string('placement', 8)->default('extra'); // main | extra
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('chat_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('reaction_type_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['message_id', 'user_id', 'reaction_type_id'], 'chat_reactions_unique');
        });

        Schema::create('chat_mutes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('lesson_id')->nullable(); // null — во всех эфирах
            $table->timestamp('until')->nullable();              // null — без срока
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::create('kinescope_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 64)->nullable();
            $table->unsignedBigInteger('lesson_id')->nullable();
            $table->string('action', 16)->nullable(); // start | end | ignored
            $table->longText('payload');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('chat_name', 64)->nullable();
            $table->string('chat_badge', 32)->nullable();
        });

        // Набор реакций по умолчанию (согласован в ТЗ). Админ меняет его в
        // «Реакциях чата»; удаляются реакции только выключением.
        $now = now();
        $main = [
            ['сердце', '2764_fe0f', '❤️'], ['лайк', '1f44d', '👍'], ['смех', '1f602', '😂'],
            ['огонь', '1f525', '🔥'], ['влюблён', '1f60d', '😍'], ['взрыв мозга', '1f92f', '🤯'],
            ['дизлайк', '1f44e', '👎'],
        ];
        $extra = [
            ['какашка', '1f4a9', '💩'], ['тошнит', '1f92e', '🤮'], ['клубничка', '1f353', '🍓'],
            ['рукопожатие', '1f91d', '🤝'], ['удивление', '1f62e', '😮'], ['думаю', '1f914', '🤔'],
            ['аплодисменты', '1f44f', '👏'], ['сто', '1f4af', '💯'], ['спасибо', '1f64f', '🙏'],
            ['плачу', '1f62d', '😭'], ['акула', '1f988', '🦈'],
        ];
        $rows = [];
        $sort = 0;
        foreach ([['main', $main], ['extra', $extra]] as [$placement, $list]) {
            foreach ($list as [$name, $code, $emoji]) {
                $rows[] = [
                    'name' => $name, 'noto_code' => $code, 'emoji' => $emoji, 'format' => 'noto',
                    'placement' => $placement, 'sort' => ++$sort, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        DB::table('chat_reaction_types')->insert($rows);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['chat_name', 'chat_badge']);
        });
        Schema::dropIfExists('kinescope_webhook_events');
        Schema::dropIfExists('chat_mutes');
        Schema::dropIfExists('chat_message_reactions');
        Schema::dropIfExists('chat_reaction_types');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('lesson_live_segments');
        Schema::dropIfExists('lesson_chats');
    }
};
