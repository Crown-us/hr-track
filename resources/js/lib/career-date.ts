export const careerDate = (v: string) =>
    new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeZone: 'UTC' }).format(
        new Date(`${v}T00:00:00Z`),
    );
