import { format as formatDate, parseISO } from 'date-fns';

export default function DateTime({ date, format = 'yyyy-MM-dd HH:mm:ss', className }: { date: string; format?: string; className?: string }) {
  if (!date) return null;
  try {
    const parsedDate = parseISO(date);
    const fnsFormat = format.replace(/YYYY/g, 'yyyy').replace(/DD/g, 'dd');
    return (
      <time dateTime={date} className={className}>
        {formatDate(parsedDate, fnsFormat)}
      </time>
    );
  } catch {
    return <span className={className}>{date}</span>;
  }
}
