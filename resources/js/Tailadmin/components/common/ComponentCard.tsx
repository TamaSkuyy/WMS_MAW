interface ComponentCardProps {
  title: string;
  children: React.ReactNode;
  className?: string;
  desc?: string;
  action?: React.ReactNode;
}

const ComponentCard: React.FC<ComponentCardProps> = ({
  title,
  children,
  className = "",
  desc = "",
  action,
}) => {
  return (
    <div
      className={`rounded-xl border border-[#E9ECEF] bg-white shadow-sm ${className}`}
    >
      {/* Card Header */}
      <div className="px-6 py-5 border-b border-[#F1F3F5] flex items-center justify-between">
        <div>
          <h3 className="text-base font-semibold text-[#1A1D23]">
            {title}
          </h3>
          {desc && (
            <p className="mt-1 text-sm text-[#6C757D]">
              {desc}
            </p>
          )}
        </div>
        {action && <div>{action}</div>}
      </div>

      {/* Card Body */}
      <div className="p-6">
        <div className="space-y-6">{children}</div>
      </div>
    </div>
  );
};

export default ComponentCard;
