/** Go back to the page the user came from; open the home page only if there is nothing to go back to. */
export function goBackOrHome(navigate) {
  if (window.history.state && window.history.state.idx > 0) navigate(-1);
  else navigate('/');
}
