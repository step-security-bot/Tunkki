import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = ["pic", "source", "badge", "player"];
  static values = { url: String, refreshInterval: Number, onlineImg: String };
  connect() {
    this.streamStatus();

    if (this.hasRefreshIntervalValue) {
      this.startRefreshing();
    }
  }
  disconnect() {
    this.stopRefreshing();
  }
  streamStatus() {
    fetch(this.urlValue).then((response) => this.setStream(response.status));
  }
  startRefreshing() {
    if (!document.hidden) {
      this.refreshTimer = setInterval(() => {
        this.streamStatus();
      }, this.refreshIntervalValue);
    } else {
      this.stopRefreshing();
    }
  }
  stopRefreshing() {
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
    }
  }
  setStream(data) {
    // console.log(data);
    if (data == 200) {
      this.badgeTarget.innerText = "ONLINE";
      this.badgeTarget.classList.add("bg-success");
      this.playerTarget.classList.remove("d-none");
      this.picTarget.setAttribute("src", this.onlineImgValue);
      this.sourceTarget.setAttribute("src", this.urlValue);
    } else {
      this.badgeTarget.innerText = "OFFLINE";
      this.badgeTarget.classList.remove("bg-success");
      this.playerTarget.classList.add("d-none");
      this.picTarget.setAttribute("src", this.offlineImgValue);
      this.sourceTarget.setAttribute("src", this.urlValue);
    }
  }
}
