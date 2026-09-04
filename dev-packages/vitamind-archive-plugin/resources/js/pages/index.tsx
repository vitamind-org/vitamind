import { useState } from 'react';
import AppLayout from '@/layouts/app/layout';
import Container from '@/components/container';
import Heading from '@/components/heading';
import { Button } from '@vitamind/ui/button';
import { Head, Link, usePage } from '@inertiajs/react';
import { CornerLeftUpIcon, FolderPlus } from 'lucide-react';
import CreateFolderForm from './components/create-folder-form';
import FileRow from './components/file-row';
import FolderRow from './components/folder-row';
import UploadFileForm from './components/upload-file-form';
import { Visibility } from './components/visibility-select';

interface FolderSummary {
  uuid: string;
  name: string;
  visibility: Visibility;
}

interface FileSummary {
  uuid: string;
  original_name: string;
  extension: string;
  size: number;
  visibility: Visibility;
}

interface BreadcrumbItem {
  uuid: string;
  name: string;
}

interface ArchivePageProps {
  folder: FolderSummary | null;
  rootLabel: string;
  defaultVisibility: Visibility;
  breadcrumb: BreadcrumbItem[];
  folders: FolderSummary[];
  files: FileSummary[];
  [key: string]: unknown;
}

export default function ArchiveIndex() {
  const page = usePage<ArchivePageProps>();
  const { folder, rootLabel, defaultVisibility, breadcrumb, folders, files } = page.props;
  const [creatingFolder, setCreatingFolder] = useState(false);

  // Root has no owner-gate of its own (ArchiveScope), so it's always
  // reachable — treat it as `workspace` for the broken-visibility check
  // rather than as `user`, which would flag every workspace-visible
  // top-level item as broken.
  const parentVisibility: Visibility = folder?.visibility ?? 'workspace';

  // breadcrumb runs top ancestor → current folder inclusive (see
  // FolderController::breadcrumb), so the entry before the last one is the
  // parent to go "up" to; with only one entry, up goes to the root.
  const parentHref = breadcrumb.length >= 2 ? `/archive/folders/${breadcrumb[breadcrumb.length - 2].uuid}` : '/archive';

  return (
    <AppLayout>
      <Head title="Archive" />
      <Container className="max-w-5xl">
        <div className="flex items-start justify-between">
          <Heading title="Archive" description="Folders and files, shared per item" />
          <div className="flex items-center gap-2">
            <Button variant="outline" onClick={() => setCreatingFolder(true)} disabled={creatingFolder}>
              <FolderPlus />
              New folder
            </Button>
            <UploadFileForm folderId={folder?.uuid ?? null} defaultVisibility={defaultVisibility} />
          </div>
        </div>

        <nav className="flex flex-wrap items-center gap-1 text-sm text-neutral-600 dark:text-neutral-400">
          <Link href="/archive" className="hover:underline">
            {rootLabel}
          </Link>
          {breadcrumb.map((item) => (
            <span key={item.uuid} className="flex items-center gap-1">
              <span>/</span>
              <Link href={`/archive/folders/${item.uuid}`} className="hover:underline">
                {item.name}
              </Link>
            </span>
          ))}
        </nav>

        <div className="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-800">
          <ul className="divide-y divide-neutral-200 dark:divide-neutral-800">
            {folder && (
              <li className="flex items-center gap-4 p-3">
                <Link href={parentHref} className="flex min-w-0 flex-1 items-center gap-2 text-neutral-600 dark:text-neutral-400">
                  <CornerLeftUpIcon className="size-4 shrink-0" />
                  <span>..</span>
                </Link>
              </li>
            )}

            {folders.length === 0 && files.length === 0 && !creatingFolder && (
              <li className="p-6 text-sm text-neutral-600 dark:text-neutral-400">This folder is empty.</li>
            )}

            {folders.map((child) => (
              <FolderRow key={`folder-${child.uuid}`} folder={child} parentVisibility={parentVisibility} />
            ))}

            {files.map((file) => (
              <FileRow key={`file-${file.uuid}`} file={file} parentVisibility={parentVisibility} />
            ))}

            {creatingFolder && (
              <CreateFolderForm parentId={folder?.uuid ?? null} defaultVisibility={defaultVisibility} onClose={() => setCreatingFolder(false)} />
            )}
          </ul>
        </div>
      </Container>
    </AppLayout>
  );
}
