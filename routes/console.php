<?php


use App\Console\Commands\{
    ChangeStorageClassCommand,
    CreateEmailForNotCreatedEmailCommand,
    EmailSendCommand,
    GenerateYandexVideoToken,
    PayoutToAuthorsCommand,
    PaymentProcessCommand,
    PostPublishCommand,
    RemoveInactiveSessionsCommand,
    RemoveNotConfirmedAccount,
    RemoveVideoNotUsingCommand,
    PruneTariffCountsCommand,
    RemoveFreeSubscriptionCommand,
    SendSubscriptionNotificationCommand,
    SendDailyReportsCommand,
    SyncFiscalReceiptsCommand,
    SyncPendingPayoutsCommand,
    TempStorageRemoveCommand,
    UpdateFinanceStatisticCommand,
    UpdatePageSubscribersCountCommand
};
use Illuminate\Support\Facades\Schedule;


Schedule::command(SendDailyReportsCommand::class)->dailyAt('00:00')->runInBackground();
Schedule::command(PaymentProcessCommand::class)->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command(EmailSendCommand::class)->everyMinute()->withoutOverlapping(10);
Schedule::command(PostPublishCommand::class)->everyMinute()->withoutOverlapping(10);
Schedule::command(UpdatePageSubscribersCountCommand::class)->everyThreeHours();
Schedule::command(UpdateFinanceStatisticCommand::class)->everyThreeHours();
Schedule::command(RemoveNotConfirmedAccount::class)->everyFiveMinutes();
Schedule::command(TempStorageRemoveCommand::class)->daily();
Schedule::command(CreateEmailForNotCreatedEmailCommand::class)->hourly();
Schedule::command(ChangeStorageClassCommand::class)->dailyAt('02:02')->runInBackground();
Schedule::command(GenerateYandexVideoToken::class)->hourly();
Schedule::command(RemoveVideoNotUsingCommand::class)->dailyAt('00:03')->withoutOverlapping()->runInBackground();
Schedule::command(RemoveInactiveSessionsCommand::class)->weekly();
Schedule::command(SendSubscriptionNotificationCommand::class)->hourly();
Schedule::command(PayoutToAuthorsCommand::class)->dailyAt('05:00')->withoutOverlapping()->runInBackground();
Schedule::command(SyncPendingPayoutsCommand::class)->everyFiveMinutes()->withoutOverlapping(10)->runInBackground();
Schedule::command(SyncFiscalReceiptsCommand::class)->everyFifteenMinutes()->withoutOverlapping(20)->runInBackground();
Schedule::command(RemoveFreeSubscriptionCommand::class)->hourly()->withoutOverlapping()->runInBackground();
Schedule::command(PruneTariffCountsCommand::class)->dailyAt('03:17')->withoutOverlapping()->runInBackground();
