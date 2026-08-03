function pad(value) {
  return String(value).padStart(2, '0')
}

export function getLocalDateInputValue(date = new Date()) {
  return [
    date.getFullYear(),
    pad(date.getMonth() + 1),
    pad(date.getDate()),
  ].join('-')
}

export function getLocalDateTimeInputValue(date = new Date()) {
  return `${getLocalDateInputValue(date)}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}
