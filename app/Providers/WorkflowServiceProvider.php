<?php

namespace App\Providers;

use App\Services\Workflows\Nodes\Apps\Ai\LlmNode;
use App\Services\Workflows\Nodes\Apps\Airtable\AirtableCreateRecordNode;
use App\Services\Workflows\Nodes\Apps\Airtable\AirtableDeleteRecordNode;
use App\Services\Workflows\Nodes\Apps\Airtable\AirtableGetRecordNode;
use App\Services\Workflows\Nodes\Apps\Airtable\AirtableListRecordsNode;
use App\Services\Workflows\Nodes\Apps\Airtable\AirtableUpdateRecordNode;
use App\Services\Workflows\Nodes\Apps\AwsS3\AwsS3DeleteObjectNode;
use App\Services\Workflows\Nodes\Apps\AwsS3\AwsS3GetObjectNode;
use App\Services\Workflows\Nodes\Apps\AwsS3\AwsS3GetUrlNode;
use App\Services\Workflows\Nodes\Apps\AwsS3\AwsS3ListObjectsNode;
use App\Services\Workflows\Nodes\Apps\AwsS3\AwsS3PutObjectNode;
use App\Services\Workflows\Nodes\Apps\Data\ArrayNode;
use App\Services\Workflows\Nodes\Apps\Data\CacheNode;
use App\Services\Workflows\Nodes\Apps\Data\DataNode;
use App\Services\Workflows\Nodes\Apps\Data\DateTimeNode;
use App\Services\Workflows\Nodes\Apps\Data\FilterNode;
use App\Services\Workflows\Nodes\Apps\Data\JsonNode;
use App\Services\Workflows\Nodes\Apps\Data\MathNode;
use App\Services\Workflows\Nodes\Apps\Data\StringNode;
use App\Services\Workflows\Nodes\Apps\Data\VariableNode;
use App\Services\Workflows\Nodes\Apps\Debug\LoggerNode;
use App\Services\Workflows\Nodes\Apps\Discord\DiscordCreateChannelNode;
use App\Services\Workflows\Nodes\Apps\Discord\DiscordGetGuildMembersNode;
use App\Services\Workflows\Nodes\Apps\Discord\DiscordSendMessageNode;
use App\Services\Workflows\Nodes\Apps\Discord\DiscordSendWebhookNode;
use App\Services\Workflows\Nodes\Apps\Dropbox\DropboxCreateFolderNode;
use App\Services\Workflows\Nodes\Apps\Dropbox\DropboxDeleteFileNode;
use App\Services\Workflows\Nodes\Apps\Dropbox\DropboxGetLinkNode;
use App\Services\Workflows\Nodes\Apps\Dropbox\DropboxListFolderNode;
use App\Services\Workflows\Nodes\Apps\Dropbox\DropboxMoveFileNode;
use App\Services\Workflows\Nodes\Apps\Ftp\FtpDeleteNode;
use App\Services\Workflows\Nodes\Apps\Ftp\FtpDownloadNode;
use App\Services\Workflows\Nodes\Apps\Ftp\FtpListFilesNode;
use App\Services\Workflows\Nodes\Apps\Ftp\FtpUploadNode;
use App\Services\Workflows\Nodes\Apps\GitHub\GitHubCreateCommentNode;
use App\Services\Workflows\Nodes\Apps\GitHub\GitHubCreateIssueNode;
use App\Services\Workflows\Nodes\Apps\GitHub\GitHubCreatePullRequestNode;
use App\Services\Workflows\Nodes\Apps\GitHub\GitHubGetRepoNode;
use App\Services\Workflows\Nodes\Apps\GitHub\GitHubListCommitsNode;
use App\Services\Workflows\Nodes\Apps\GitHub\GitHubListIssuesNode;
use App\Services\Workflows\Nodes\Apps\GitHub\GitHubListPullRequestsNode;
use App\Services\Workflows\Nodes\Apps\GitHub\GitHubListReposNode;
use App\Services\Workflows\Nodes\Apps\GitLab\GitLabCreateIssueNode;
use App\Services\Workflows\Nodes\Apps\GitLab\GitLabCreateMergeRequestNode;
use App\Services\Workflows\Nodes\Apps\GitLab\GitLabListIssuesNode;
use App\Services\Workflows\Nodes\Apps\GitLab\GitLabListMergeRequestsNode;
use App\Services\Workflows\Nodes\Apps\GitLab\GitLabListPipelinesNode;
use App\Services\Workflows\Nodes\Apps\GitLab\GitLabTriggerPipelineNode;
use App\Services\Workflows\Nodes\Apps\GitLab\GitLabUpdateIssueNode;
use App\Services\Workflows\Nodes\Apps\Google\GmailAddLabelNode;
use App\Services\Workflows\Nodes\Apps\Google\GmailCreateDraftNode;
use App\Services\Workflows\Nodes\Apps\Google\GmailDeleteMessageNode;
use App\Services\Workflows\Nodes\Apps\Google\GmailGetMessageNode;
use App\Services\Workflows\Nodes\Apps\Google\GmailListLabelsNode;
use App\Services\Workflows\Nodes\Apps\Google\GmailListMessagesNode;
use App\Services\Workflows\Nodes\Apps\Google\GmailModifyMessageNode;
use App\Services\Workflows\Nodes\Apps\Google\GmailReplyToMessageNode;
use App\Services\Workflows\Nodes\Apps\Google\GmailSendEmailNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleCalendarCreateEventNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleCalendarDeleteEventNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleCalendarGetEventNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleCalendarListCalendarsNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleCalendarListEventsNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleCalendarUpdateEventNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleDriveCreateFolderNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleDriveDeleteFileNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleDriveDownloadFileNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleDriveGetFileNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleDriveListFilesNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleDriveShareFileNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleDriveUpdateFileNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleDriveUploadFileNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleSheetsAppendRowNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleSheetsClearRangeNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleSheetsCreateSpreadsheetNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleSheetsDeleteRowsNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleSheetsGetRowsNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleSheetsGetSpreadsheetInfoNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleSheetsLookupRowsNode;
use App\Services\Workflows\Nodes\Apps\Google\GoogleSheetsUpdateRowNode;
use App\Services\Workflows\Nodes\Apps\Hubspot\HubspotCreateCompanyNode;
use App\Services\Workflows\Nodes\Apps\Hubspot\HubspotCreateContactNode;
use App\Services\Workflows\Nodes\Apps\Hubspot\HubspotCreateDealNode;
use App\Services\Workflows\Nodes\Apps\Hubspot\HubspotGetContactNode;
use App\Services\Workflows\Nodes\Apps\Hubspot\HubspotListCompaniesNode;
use App\Services\Workflows\Nodes\Apps\Hubspot\HubspotListContactsNode;
use App\Services\Workflows\Nodes\Apps\Hubspot\HubspotListDealsNode;
use App\Services\Workflows\Nodes\Apps\Hubspot\HubspotSearchContactsNode;
use App\Services\Workflows\Nodes\Apps\Jira\JiraAddCommentNode;
use App\Services\Workflows\Nodes\Apps\Jira\JiraCreateIssueNode;
use App\Services\Workflows\Nodes\Apps\Jira\JiraGetIssueNode;
use App\Services\Workflows\Nodes\Apps\Jira\JiraSearchIssuesNode;
use App\Services\Workflows\Nodes\Apps\Jira\JiraTransitionIssueNode;
use App\Services\Workflows\Nodes\Apps\Jira\JiraUpdateIssueNode;
use App\Services\Workflows\Nodes\Apps\Linear\LinearCreateCommentNode;
use App\Services\Workflows\Nodes\Apps\Linear\LinearCreateIssueNode;
use App\Services\Workflows\Nodes\Apps\Linear\LinearListIssuesNode;
use App\Services\Workflows\Nodes\Apps\Linear\LinearUpdateIssueNode;
use App\Services\Workflows\Nodes\Apps\Mail\EmailSendNode;
use App\Services\Workflows\Nodes\Apps\Mailchimp\MailchimpAddSubscriberNode;
use App\Services\Workflows\Nodes\Apps\Mailchimp\MailchimpAddTagNode;
use App\Services\Workflows\Nodes\Apps\Mailchimp\MailchimpGetSubscriberNode;
use App\Services\Workflows\Nodes\Apps\Mailchimp\MailchimpListCampaignsNode;
use App\Services\Workflows\Nodes\Apps\Mailchimp\MailchimpListListsNode;
use App\Services\Workflows\Nodes\Apps\Mailchimp\MailchimpListSubscribersNode;
use App\Services\Workflows\Nodes\Apps\Mailchimp\MailchimpRemoveSubscriberNode;
use App\Services\Workflows\Nodes\Apps\Mailchimp\MailchimpUpdateSubscriberNode;
use App\Services\Workflows\Nodes\Apps\Mongodb\MongodbDeleteOneNode;
use App\Services\Workflows\Nodes\Apps\Mongodb\MongodbFindNode;
use App\Services\Workflows\Nodes\Apps\Mongodb\MongodbFindOneNode;
use App\Services\Workflows\Nodes\Apps\Mongodb\MongodbInsertManyNode;
use App\Services\Workflows\Nodes\Apps\Mongodb\MongodbInsertOneNode;
use App\Services\Workflows\Nodes\Apps\Mongodb\MongodbUpdateOneNode;
use App\Services\Workflows\Nodes\Apps\Mysql\MysqlDeleteNode;
use App\Services\Workflows\Nodes\Apps\Mysql\MysqlInsertNode;
use App\Services\Workflows\Nodes\Apps\Mysql\MysqlRawQueryNode;
use App\Services\Workflows\Nodes\Apps\Mysql\MysqlSelectNode;
use App\Services\Workflows\Nodes\Apps\Mysql\MysqlUpdateNode;
use App\Services\Workflows\Nodes\Apps\Notion\NotionAppendBlocksNode;
use App\Services\Workflows\Nodes\Apps\Notion\NotionCreatePageNode;
use App\Services\Workflows\Nodes\Apps\Notion\NotionGetPageNode;
use App\Services\Workflows\Nodes\Apps\Notion\NotionListDatabasesNode;
use App\Services\Workflows\Nodes\Apps\Notion\NotionQueryDatabaseNode;
use App\Services\Workflows\Nodes\Apps\Notion\NotionUpdatePageNode;
use App\Services\Workflows\Nodes\Apps\OpenAi\OpenAiChatCompletionNode;
use App\Services\Workflows\Nodes\Apps\OpenAi\OpenAiEmbeddingsNode;
use App\Services\Workflows\Nodes\Apps\OpenAi\OpenAiImageGenerationNode;
use App\Services\Workflows\Nodes\Apps\Postgres\PostgresQueryNode;
use App\Services\Workflows\Nodes\Apps\Redis\RedisDeleteNode;
use App\Services\Workflows\Nodes\Apps\Redis\RedisGetNode;
use App\Services\Workflows\Nodes\Apps\Redis\RedisIncrementNode;
use App\Services\Workflows\Nodes\Apps\Redis\RedisKeysNode;
use App\Services\Workflows\Nodes\Apps\Redis\RedisPublishNode;
use App\Services\Workflows\Nodes\Apps\Redis\RedisSetNode;
use App\Services\Workflows\Nodes\Apps\Salesforce\SalesforceCreateRecordNode;
use App\Services\Workflows\Nodes\Apps\Salesforce\SalesforceDeleteRecordNode;
use App\Services\Workflows\Nodes\Apps\Salesforce\SalesforceGetRecordNode;
use App\Services\Workflows\Nodes\Apps\Salesforce\SalesforceQueryNode;
use App\Services\Workflows\Nodes\Apps\Salesforce\SalesforceUpdateRecordNode;
use App\Services\Workflows\Nodes\Apps\Sendgrid\SendgridAddContactNode;
use App\Services\Workflows\Nodes\Apps\Sendgrid\SendgridListContactsNode;
use App\Services\Workflows\Nodes\Apps\Sendgrid\SendgridSendEmailNode;
use App\Services\Workflows\Nodes\Apps\Sendgrid\SendgridSendTemplateNode;
use App\Services\Workflows\Nodes\Apps\Slack\SlackCreateChannelNode;
use App\Services\Workflows\Nodes\Apps\Slack\SlackGetChannelHistoryNode;
use App\Services\Workflows\Nodes\Apps\Slack\SlackInviteToChannelNode;
use App\Services\Workflows\Nodes\Apps\Slack\SlackListChannelsNode;
use App\Services\Workflows\Nodes\Apps\Slack\SlackListUsersNode;
use App\Services\Workflows\Nodes\Apps\Slack\SlackPostMessageNode;
use App\Services\Workflows\Nodes\Apps\Slack\SlackUploadFileNode;
use App\Services\Workflows\Nodes\Apps\Stripe\StripeCancelSubscriptionNode;
use App\Services\Workflows\Nodes\Apps\Stripe\StripeCreateChargeNode;
use App\Services\Workflows\Nodes\Apps\Stripe\StripeCreateCustomerNode;
use App\Services\Workflows\Nodes\Apps\Stripe\StripeCreateInvoiceNode;
use App\Services\Workflows\Nodes\Apps\Stripe\StripeCreatePriceNode;
use App\Services\Workflows\Nodes\Apps\Stripe\StripeCreateProductNode;
use App\Services\Workflows\Nodes\Apps\Stripe\StripeCreateSubscriptionNode;
use App\Services\Workflows\Nodes\Apps\Stripe\StripeGetBalanceNode;
use App\Services\Workflows\Nodes\Apps\Stripe\StripeListPaymentsNode;
use App\Services\Workflows\Nodes\Apps\Stripe\StripeListSubscriptionsNode;
use App\Services\Workflows\Nodes\Apps\Stripe\StripeRetrieveCustomerNode;
use App\Services\Workflows\Nodes\Apps\Telegram\TelegramDeleteMessageNode;
use App\Services\Workflows\Nodes\Apps\Telegram\TelegramEditMessageNode;
use App\Services\Workflows\Nodes\Apps\Telegram\TelegramGetUpdatesNode;
use App\Services\Workflows\Nodes\Apps\Telegram\TelegramSendDocumentNode;
use App\Services\Workflows\Nodes\Apps\Telegram\TelegramSendMessageNode;
use App\Services\Workflows\Nodes\Apps\Telegram\TelegramSendPhotoNode;
use App\Services\Workflows\Nodes\Apps\Trello\TrelloAddCommentNode;
use App\Services\Workflows\Nodes\Apps\Trello\TrelloCreateCardNode;
use App\Services\Workflows\Nodes\Apps\Trello\TrelloListCardsNode;
use App\Services\Workflows\Nodes\Apps\Trello\TrelloMoveCardNode;
use App\Services\Workflows\Nodes\Apps\Trello\TrelloUpdateCardNode;
use App\Services\Workflows\Nodes\Apps\Twilio\TwilioCheckVerificationNode;
use App\Services\Workflows\Nodes\Apps\Twilio\TwilioMakeCallNode;
use App\Services\Workflows\Nodes\Apps\Twilio\TwilioSendSmsNode;
use App\Services\Workflows\Nodes\Apps\Twilio\TwilioSendVerificationNode;
use App\Services\Workflows\Nodes\Apps\Twilio\TwilioSendWhatsappNode;
use App\Services\Workflows\Nodes\Apps\Twitch\TwitchGetChannelInfoNode;
use App\Services\Workflows\Nodes\Apps\Twitch\TwitchGetStreamsNode;
use App\Services\Workflows\Nodes\Apps\Twitch\TwitchGetUserNode;
use App\Services\Workflows\Nodes\Apps\Twitter\TwitterDeleteTweetNode;
use App\Services\Workflows\Nodes\Apps\Twitter\TwitterGetUserNode;
use App\Services\Workflows\Nodes\Apps\Twitter\TwitterPostTweetNode;
use App\Services\Workflows\Nodes\Apps\Twitter\TwitterSearchTweetsNode;
use App\Services\Workflows\Nodes\Core\AgentNode;
use App\Services\Workflows\Nodes\Core\CodeNode;
use App\Services\Workflows\Nodes\Core\HttpRequestNode;
use App\Services\Workflows\Nodes\Core\HumanApprovalNode;
use App\Services\Workflows\Nodes\Core\SetVariableNode;
use App\Services\Workflows\Nodes\Core\SubWorkflowNode;
use App\Services\Workflows\Nodes\Core\TransformNode;
use App\Services\Workflows\Nodes\Core\TriggerNode;
use App\Services\Workflows\Nodes\Flow\ConditionNode;
use App\Services\Workflows\Nodes\Flow\DelayNode;
use App\Services\Workflows\Nodes\Flow\LoopNode;
use App\Services\Workflows\Nodes\Flow\MergeNode;
use App\Services\Workflows\Nodes\Flow\RetryNode;
use App\Services\Workflows\Nodes\Flow\TryCatchNode;
use App\Services\Workflows\Nodes\Flow\WaitNode;
use App\Services\Workflows\Nodes\NodeDefinition;
use App\Services\Workflows\Nodes\NodeRegistry;
use Illuminate\Support\ServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{
    /**
     * Every node the engine knows how to run. The `nodes` table is synced from this
     * list (see NodeCatalogSync), so adding a node here is all that is required for it
     * to appear in the builder palette and be validated at publish time.
     *
     * @var array<int, class-string<NodeDefinition>>
     */
    private const NODES = [
        // Core flow control — executed by the engine itself.
        AgentNode::class,
        ConditionNode::class,
        MergeNode::class,
        TransformNode::class,
        DelayNode::class,
        LoopNode::class,
        HumanApprovalNode::class,
        SubWorkflowNode::class,
        RetryNode::class,
        TryCatchNode::class,
        WaitNode::class,
        TriggerNode::class,
        SetVariableNode::class,

        // Connectors — executed by their own definition.
        HttpRequestNode::class,
        CodeNode::class,

        // Communication
        EmailSendNode::class,
        SlackPostMessageNode::class,
        SlackCreateChannelNode::class,
        SlackInviteToChannelNode::class,
        SlackGetChannelHistoryNode::class,
        SlackUploadFileNode::class,
        SlackListChannelsNode::class,
        SlackListUsersNode::class,
        DiscordSendMessageNode::class,
        DiscordSendWebhookNode::class,
        DiscordCreateChannelNode::class,
        DiscordGetGuildMembersNode::class,
        TelegramSendMessageNode::class,
        TelegramSendPhotoNode::class,
        TelegramSendDocumentNode::class,
        TelegramGetUpdatesNode::class,
        TelegramEditMessageNode::class,
        TelegramDeleteMessageNode::class,

        // Development tools
        GitHubListReposNode::class,
        GitHubGetRepoNode::class,
        GitHubCreateIssueNode::class,
        GitHubListIssuesNode::class,
        GitHubCreatePullRequestNode::class,
        GitHubListPullRequestsNode::class,
        GitHubCreateCommentNode::class,
        GitHubListCommitsNode::class,
        GitLabListIssuesNode::class,
        GitLabCreateIssueNode::class,
        GitLabUpdateIssueNode::class,
        GitLabListMergeRequestsNode::class,
        GitLabCreateMergeRequestNode::class,
        GitLabTriggerPipelineNode::class,
        GitLabListPipelinesNode::class,
        LinearCreateIssueNode::class,
        LinearUpdateIssueNode::class,
        LinearListIssuesNode::class,
        LinearCreateCommentNode::class,
        JiraCreateIssueNode::class,
        JiraUpdateIssueNode::class,
        JiraGetIssueNode::class,
        JiraSearchIssuesNode::class,
        JiraAddCommentNode::class,
        JiraTransitionIssueNode::class,

        // AI & machine learning
        LlmNode::class,
        OpenAiChatCompletionNode::class,
        OpenAiEmbeddingsNode::class,
        OpenAiImageGenerationNode::class,

        // Google Workspace
        GmailSendEmailNode::class,
        GmailReplyToMessageNode::class,
        GmailGetMessageNode::class,
        GmailListMessagesNode::class,
        GmailModifyMessageNode::class,
        GmailAddLabelNode::class,
        GmailListLabelsNode::class,
        GmailDeleteMessageNode::class,
        GmailCreateDraftNode::class,
        GoogleCalendarListEventsNode::class,
        GoogleCalendarGetEventNode::class,
        GoogleCalendarCreateEventNode::class,
        GoogleCalendarUpdateEventNode::class,
        GoogleCalendarDeleteEventNode::class,
        GoogleCalendarListCalendarsNode::class,
        GoogleDriveListFilesNode::class,
        GoogleDriveGetFileNode::class,
        GoogleDriveDownloadFileNode::class,
        GoogleDriveUploadFileNode::class,
        GoogleDriveUpdateFileNode::class,
        GoogleDriveCreateFolderNode::class,
        GoogleDriveDeleteFileNode::class,
        GoogleDriveShareFileNode::class,
        GoogleSheetsGetRowsNode::class,
        GoogleSheetsAppendRowNode::class,
        GoogleSheetsUpdateRowNode::class,
        GoogleSheetsClearRangeNode::class,
        GoogleSheetsDeleteRowsNode::class,
        GoogleSheetsLookupRowsNode::class,
        GoogleSheetsCreateSpreadsheetNode::class,
        GoogleSheetsGetSpreadsheetInfoNode::class,

        // Data & storage
        NotionQueryDatabaseNode::class,
        NotionCreatePageNode::class,
        NotionUpdatePageNode::class,
        NotionGetPageNode::class,
        NotionAppendBlocksNode::class,
        NotionListDatabasesNode::class,
        PostgresQueryNode::class,
        MongodbFindNode::class,
        MongodbFindOneNode::class,
        MongodbInsertOneNode::class,
        MongodbInsertManyNode::class,
        MongodbUpdateOneNode::class,
        MongodbDeleteOneNode::class,
        MysqlSelectNode::class,
        MysqlInsertNode::class,
        MysqlUpdateNode::class,
        MysqlDeleteNode::class,
        MysqlRawQueryNode::class,

        // File storage & cloud
        AwsS3GetObjectNode::class,
        AwsS3ListObjectsNode::class,
        AwsS3PutObjectNode::class,
        AwsS3DeleteObjectNode::class,
        AwsS3GetUrlNode::class,
        DropboxCreateFolderNode::class,
        DropboxListFolderNode::class,
        DropboxMoveFileNode::class,
        DropboxDeleteFileNode::class,
        DropboxGetLinkNode::class,
        FtpUploadNode::class,
        FtpDeleteNode::class,
        FtpDownloadNode::class,
        FtpListFilesNode::class,

        // Email & communication
        SendgridSendEmailNode::class,
        SendgridSendTemplateNode::class,
        SendgridAddContactNode::class,
        SendgridListContactsNode::class,
        MailchimpAddSubscriberNode::class,
        MailchimpUpdateSubscriberNode::class,
        MailchimpGetSubscriberNode::class,
        MailchimpRemoveSubscriberNode::class,
        MailchimpListSubscribersNode::class,
        MailchimpListCampaignsNode::class,
        MailchimpListListsNode::class,
        MailchimpAddTagNode::class,
        TwilioSendSmsNode::class,
        TwilioSendWhatsappNode::class,
        TwilioMakeCallNode::class,
        TwilioSendVerificationNode::class,
        TwilioCheckVerificationNode::class,

        // Project management
        TrelloCreateCardNode::class,
        TrelloUpdateCardNode::class,
        TrelloMoveCardNode::class,
        TrelloListCardsNode::class,
        TrelloAddCommentNode::class,

        // CRM & sales
        HubspotCreateContactNode::class,
        HubspotCreateDealNode::class,
        HubspotCreateCompanyNode::class,
        HubspotSearchContactsNode::class,
        HubspotListContactsNode::class,
        HubspotListDealsNode::class,
        HubspotListCompaniesNode::class,
        HubspotGetContactNode::class,
        SalesforceCreateRecordNode::class,
        SalesforceUpdateRecordNode::class,
        SalesforceGetRecordNode::class,
        SalesforceQueryNode::class,
        SalesforceDeleteRecordNode::class,

        // Data backends
        AirtableGetRecordNode::class,
        AirtableListRecordsNode::class,
        AirtableCreateRecordNode::class,
        AirtableUpdateRecordNode::class,
        AirtableDeleteRecordNode::class,
        RedisGetNode::class,
        RedisSetNode::class,
        RedisDeleteNode::class,
        RedisIncrementNode::class,
        RedisKeysNode::class,
        RedisPublishNode::class,

        // Social & media
        TwitterPostTweetNode::class,
        TwitterDeleteTweetNode::class,
        TwitterSearchTweetsNode::class,
        TwitterGetUserNode::class,
        TwitchGetStreamsNode::class,
        TwitchGetUserNode::class,
        TwitchGetChannelInfoNode::class,

        // Payments & billing
        StripeCreateCustomerNode::class,
        StripeRetrieveCustomerNode::class,
        StripeCreateChargeNode::class,
        StripeCreateInvoiceNode::class,
        StripeListPaymentsNode::class,
        StripeGetBalanceNode::class,
        StripeCreateSubscriptionNode::class,
        StripeListSubscriptionsNode::class,
        StripeCancelSubscriptionNode::class,
        StripeCreateProductNode::class,
        StripeCreatePriceNode::class,

        // Utility nodes
        DataNode::class,
        ArrayNode::class,
        JsonNode::class,
        StringNode::class,
        MathNode::class,
        DateTimeNode::class,
        FilterNode::class,
        CacheNode::class,
        VariableNode::class,
        LoggerNode::class,
    ];

    public function register(): void
    {
        $this->app->singleton(NodeRegistry::class, function (): NodeRegistry {
            $registry = new NodeRegistry;

            foreach (self::NODES as $node) {
                $registry->register(new $node);
            }

            return $registry;
        });
    }
}
