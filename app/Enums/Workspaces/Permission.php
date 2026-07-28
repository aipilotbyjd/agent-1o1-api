<?php

namespace App\Enums\Workspaces;

enum Permission: string
{
    // Workspace
    case WorkspaceView = 'workspace.view';
    case WorkspaceUpdate = 'workspace.update';
    case WorkspaceDelete = 'workspace.delete';
    case WorkspaceUsageView = 'workspace.usage.view';

    // Members & invitations
    case MemberView = 'member.view';
    case MemberInvite = 'member.invite';
    case MemberRoleUpdate = 'member.role.update';
    case MemberRemove = 'member.remove';
    case InvitationView = 'invitation.view';

    // Notifications
    case NotificationChannelView = 'notification-channel.view';
    case NotificationChannelManage = 'notification-channel.manage';

    // Environments / Git sync / Document embeddings
    case EnvironmentView = 'environment.view';
    case EnvironmentManage = 'environment.manage';
    case GitSyncView = 'git-sync.view';
    case GitSyncManage = 'git-sync.manage';
    case DocumentEmbeddingView = 'document-embedding.view';
    case DocumentEmbeddingManage = 'document-embedding.manage';

    // Agents
    case AgentView = 'agent.view';
    case AgentManage = 'agent.manage';
    case AgentChat = 'agent.chat';
    case AgentTemplateUse = 'agent.template.use';
    case AgentToolsSync = 'agent.tools.sync';
    case AgentSkillsSync = 'agent.skills.sync';
    case AgentKnowledgeView = 'agent.knowledge.view';
    case AgentKnowledgeManage = 'agent.knowledge.manage';
    case AgentMemoryView = 'agent.memory.view';
    case AgentMemoryManage = 'agent.memory.manage';
    case AgentSkillView = 'agent.skill.view';
    case AgentSkillManage = 'agent.skill.manage';
    case AgentSkillReferenceView = 'agent.skill-reference.view';
    case AgentSkillReferenceManage = 'agent.skill-reference.manage';
    case AgentSkillScriptView = 'agent.skill-script.view';
    case AgentSkillScriptManage = 'agent.skill-script.manage';
    case AgentVersionView = 'agent.version.view';
    case AgentVersionManage = 'agent.version.manage';
    case AgentEvalView = 'agent.eval.view';
    case AgentEvalManage = 'agent.eval.manage';
    case AgentEvalRun = 'agent.eval.run';
    case AgentAnalyticsView = 'agent.analytics.view';

    // Tools / Nodes / Credentials / Variables
    case ToolView = 'tool.view';
    case ToolManage = 'tool.manage';
    case NodeView = 'node.view';
    case NodeManage = 'node.manage';
    case CredentialView = 'credential.view';
    case CredentialManage = 'credential.manage';
    case VariableView = 'variable.view';
    case VariableManage = 'variable.manage';

    // Workflows
    case WorkflowView = 'workflow.view';
    case WorkflowManage = 'workflow.manage';
    case WorkflowPublish = 'workflow.publish';
    case WorkflowShareView = 'workflow.share.view';
    case WorkflowShareManage = 'workflow.share.manage';
    case WorkflowCloneShared = 'workflow.clone-shared';
    case WorkflowVersionView = 'workflow.version.view';
    case WorkflowVersionManage = 'workflow.version.manage';
    case WorkflowApprovalView = 'workflow.approval.view';
    case WorkflowApprovalRequest = 'workflow.approval.request';
    case WorkflowApprovalReview = 'workflow.approval.review';
    case WorkflowContractSnapshotView = 'workflow.contract-snapshot.view';
    case WorkflowContractSnapshotManage = 'workflow.contract-snapshot.manage';
    case WorkflowContractTestRunView = 'workflow.contract-test-run.view';
    case WorkflowContractTestRunManage = 'workflow.contract-test-run.manage';
    case WorkflowEnvironmentReleaseView = 'workflow.environment-release.view';
    case WorkflowEnvironmentReleaseManage = 'workflow.environment-release.manage';
    case WorkflowBuilderUse = 'workflow.builder.use';
    case WorkflowTemplateUse = 'workflow.template.use';
    case WorkflowTrigger = 'workflow.trigger';

    // Runs
    case RunView = 'run.view';
    case RunLogView = 'run.log.view';
    case RunApprovalReview = 'run.approval.review';
    case RunReplayPackView = 'run.replay-pack.view';
    case RunReplayPackManage = 'run.replay-pack.manage';

    // Triggers
    case TriggerView = 'trigger.view';
    case TriggerManage = 'trigger.manage';
    case TriggerRun = 'trigger.run';
    case TriggerTokenRotate = 'trigger.token.rotate';
    case TriggerEventView = 'trigger-event.view';

    // Billing
    case BillingView = 'billing.view';
    case BillingManage = 'billing.manage';

    /**
     * Permissions granted to every workspace member, including Viewer — read-only access.
     *
     * @return array<int, self>
     */
    public static function viewerGrants(): array
    {
        return [
            self::WorkspaceView,
            self::MemberView,
            self::EnvironmentView,
            self::DocumentEmbeddingView,
            self::AgentView,
            self::AgentKnowledgeView,
            self::AgentMemoryView,
            self::AgentSkillView,
            self::AgentSkillReferenceView,
            self::AgentSkillScriptView,
            self::AgentVersionView,
            self::AgentEvalView,
            self::AgentAnalyticsView,
            self::ToolView,
            self::NodeView,
            self::CredentialView,
            self::VariableView,
            self::WorkflowView,
            self::WorkflowShareView,
            self::WorkflowVersionView,
            self::WorkflowApprovalView,
            self::WorkflowContractSnapshotView,
            self::WorkflowContractTestRunView,
            self::WorkflowEnvironmentReleaseView,
            self::RunView,
            self::RunLogView,
            self::RunReplayPackView,
            self::TriggerView,
            self::TriggerEventView,
        ];
    }

    /**
     * Additional grants for Member over Viewer — "use"/interactive actions that don't author content.
     *
     * @return array<int, self>
     */
    public static function memberGrants(): array
    {
        return [
            self::AgentChat,
            self::AgentTemplateUse,
            self::AgentEvalRun,
            self::WorkflowCloneShared,
            self::WorkflowBuilderUse,
            self::WorkflowTemplateUse,
            self::WorkflowTrigger,
            self::TriggerRun,
        ];
    }

    /**
     * Additional grants for Editor over Member — full CRUD authoring of workspace content.
     *
     * @return array<int, self>
     */
    public static function editorGrants(): array
    {
        return [
            self::AgentManage,
            self::AgentKnowledgeManage,
            self::AgentMemoryManage,
            self::AgentSkillManage,
            self::AgentSkillReferenceManage,
            self::AgentSkillScriptManage,
            self::AgentVersionManage,
            self::AgentEvalManage,
            self::ToolManage,
            self::NodeManage,
            self::VariableManage,
            self::WorkflowManage,
            self::WorkflowPublish,
            self::WorkflowShareManage,
            self::WorkflowVersionManage,
            self::WorkflowApprovalRequest,
            self::WorkflowContractSnapshotManage,
            self::WorkflowContractTestRunManage,
            self::TriggerManage,
        ];
    }

    /**
     * Additional grants for Admin over Editor — workspace settings, people, secrets, and deployments.
     *
     * @return array<int, self>
     */
    public static function adminGrants(): array
    {
        return [
            self::WorkspaceUpdate,
            self::WorkspaceUsageView,
            self::MemberInvite,
            self::MemberRoleUpdate,
            self::MemberRemove,
            self::InvitationView,
            self::NotificationChannelView,
            self::NotificationChannelManage,
            self::EnvironmentManage,
            self::GitSyncView,
            self::GitSyncManage,
            self::DocumentEmbeddingManage,
            self::CredentialManage,
            self::AgentToolsSync,
            self::AgentSkillsSync,
            self::WorkflowApprovalReview,
            self::WorkflowEnvironmentReleaseManage,
            self::RunApprovalReview,
            self::RunReplayPackManage,
            self::TriggerTokenRotate,
            self::BillingView,
            self::BillingManage,
        ];
    }

    /**
     * Additional grants for Owner over Admin.
     *
     * @return array<int, self>
     */
    public static function ownerGrants(): array
    {
        return [
            self::WorkspaceDelete,
        ];
    }
}
