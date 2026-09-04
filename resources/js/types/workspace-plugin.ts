// Host's own contract for what it expects from vitamind/workspace-plugin,
// consumed via usePlugin('workspace-plugin') — see realtime-plugin.ts for
// why this isn't `import type` from '@plugin/workspace-plugin/...' directly
// (TS2709: ambient wildcard module can't carry precise named types).
import type { ComponentType } from 'react';

export type WorkspacePlugin = {
  WorkspaceSwitch?: ComponentType;
};
